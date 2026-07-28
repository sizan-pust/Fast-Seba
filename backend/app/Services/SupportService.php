<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportService
{
    public function __construct(
        protected NotificationInboxService $notifications
    ) {
    }

    public function userTickets(
        User $user,
        int $perPage
    ): LengthAwarePaginator {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->with(['type', 'messages.sender'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(
        User $user,
        array $data,
        array $attachments = []
    ): SupportTicket {
        return DB::transaction(function () use ($user, $data, $attachments): SupportTicket {
            if (
                ! empty($data['order_id'])
                && ! \App\Models\Order::query()
                    ->where('user_id', $user->id)
                    ->whereKey($data['order_id'])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'order_id' => 'The selected order does not belong to this user.',
                ]);
            }

            $ticket = SupportTicket::query()->create([
                'ticket_type_id' => $data['ticket_type_id'],
                'user_id' => $user->id,
                'order_id' => $data['order_id'] ?? null,
                'subject' => $data['subject'],
                'email' => $data['email'] ?? $user->email,
                'description' => $data['description'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => 'open',
                'last_replied_at' => now(),
            ]);

            $message = SupportTicketMessage::query()->create([
                'ticket_id' => $ticket->id,
                'sender_id' => $user->id,
                'sender_role' => 'customer',
                'message' => $data['description'],
            ]);

            $this->attach($message, $attachments);

            return $ticket->fresh(['type', 'messages.sender']);
        });
    }

    public function userTicket(User $user, string $uuid): SupportTicket
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->with(['type', 'order', 'messages.sender'])
            ->firstOrFail();
    }

    public function userReply(
        User $user,
        string $uuid,
        string $messageText,
        array $attachments = []
    ): SupportTicket {
        $ticket = SupportTicket::query()
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages([
                'ticket' => 'Closed tickets cannot receive new messages.',
            ]);
        }

        $message = SupportTicketMessage::query()->create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'sender_role' => 'customer',
            'message' => $messageText,
        ]);

        $this->attach($message, $attachments);

        $ticket->update([
            'status' => $ticket->status === 'resolved' ? 'reopen' : $ticket->status,
            'last_replied_at' => now(),
        ]);

        return $ticket->fresh(['type', 'messages.sender']);
    }

    public function close(User $user, string $uuid): SupportTicket
    {
        $ticket = SupportTicket::query()
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return $ticket->fresh(['type', 'messages.sender']);
    }

    public function adminTickets(
        int $perPage,
        ?string $status,
        ?string $priority
    ): LengthAwarePaginator {
        return SupportTicket::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($priority, fn ($query) => $query->where('priority', $priority))
            ->with(['type', 'user', 'order', 'assignee', 'messages.sender'])
            ->latest('last_replied_at')
            ->paginate($perPage);
    }

    public function adminReply(
        User $admin,
        int $id,
        string $messageText,
        bool $internal,
        array $attachments = []
    ): SupportTicket {
        return DB::transaction(function () use (
            $admin,
            $id,
            $messageText,
            $internal,
            $attachments
        ): SupportTicket {
            $ticket = SupportTicket::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($id);

            $message = SupportTicketMessage::query()->create([
                'ticket_id' => $ticket->id,
                'sender_id' => $admin->id,
                'sender_role' => 'admin',
                'message' => $messageText,
                'is_internal' => $internal,
            ]);

            $this->attach($message, $attachments);

            $ticket->update([
                'assigned_to' => $ticket->assigned_to ?: $admin->id,
                'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
                'last_replied_at' => now(),
            ]);

            if (! $internal && $ticket->user) {
                $this->notifications->notifyUser(
                    $ticket->user,
                    'Support ticket updated',
                    $messageText,
                    'support_ticket',
                    ['ticket_uuid' => $ticket->uuid]
                );
            }

            return $ticket->fresh([
                'type',
                'user',
                'order',
                'assignee',
                'messages.sender',
            ]);
        });
    }

    public function updateStatus(
        User $admin,
        int $id,
        string $status,
        ?int $assignedTo
    ): SupportTicket {
        $ticket = SupportTicket::query()
            ->with('user')
            ->findOrFail($id);

        $ticket->update([
            'status' => $status,
            'assigned_to' => $assignedTo ?? $ticket->assigned_to,
            'resolved_at' => $status === 'resolved' ? now() : $ticket->resolved_at,
            'closed_at' => $status === 'closed' ? now() : $ticket->closed_at,
        ]);

        if ($ticket->user) {
            $this->notifications->notifyUser(
                $ticket->user,
                'Support ticket status changed',
                'Your ticket is now '.str_replace('_', ' ', $status).'.',
                'support_ticket',
                ['ticket_uuid' => $ticket->uuid, 'status' => $status]
            );
        }

        return $ticket->fresh([
            'type',
            'user',
            'order',
            'assignee',
            'messages.sender',
        ]);
    }

    private function attach(
        SupportTicketMessage $message,
        array $attachments
    ): void {
        foreach ($attachments as $attachment) {
            if ($attachment instanceof UploadedFile) {
                $message
                    ->addMedia($attachment)
                    ->toMediaCollection('support_attachments');
            }
        }
    }
}
