<?php

namespace App\Services;

use App\Models\Prescription;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PrescriptionService
{
    public function __construct(
        protected NotificationInboxService $notifications
    ) {
    }

    public function create(
        User $user,
        array $data,
        array $files
    ): Prescription {
        return DB::transaction(function () use ($user, $data, $files): Prescription {
            $items = $data['items'] ?? [];
            unset($data['items'], $data['files']);

            $prescription = Prescription::query()->create(array_merge($data, [
                'user_id' => $user->id,
                'status' => 'pending',
            ]));

            foreach ($items as $item) {
                $prescription->items()->create($item);
            }

            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $prescription
                        ->addMedia($file)
                        ->toMediaCollection('prescription_files');
                }
            }

            return $prescription->fresh([
                'items',
                'user',
                'seller',
                'store',
                'reviewer',
            ]);
        });
    }

    public function userPrescriptions(
        User $user,
        int $perPage,
        ?string $status = null
    ): LengthAwarePaginator {
        return Prescription::query()
            ->where('user_id', $user->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['items', 'seller', 'store', 'reviewer'])
            ->latest()
            ->paginate($perPage);
    }

    public function userPrescription(
        User $user,
        string $uuid
    ): Prescription {
        return Prescription::query()
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->with(['items', 'seller', 'store', 'reviewer', 'order'])
            ->firstOrFail();
    }

    public function cancel(
        User $user,
        string $uuid
    ): Prescription {
        $prescription = Prescription::query()
            ->where('user_id', $user->id)
            ->where('uuid', $uuid)
            ->firstOrFail();

        if (! in_array($prescription->status, ['pending', 'under_review'], true)) {
            throw ValidationException::withMessages([
                'prescription' => 'This prescription can no longer be cancelled.',
            ]);
        }

        $prescription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $prescription->fresh(['items', 'seller', 'store', 'reviewer']);
    }

    public function adminPrescriptions(
        int $perPage,
        ?string $status,
        ?int $sellerId
    ): LengthAwarePaginator {
        return Prescription::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($sellerId, fn ($query) => $query->where('seller_id', $sellerId))
            ->with(['items', 'user', 'seller', 'store', 'reviewer', 'order'])
            ->latest()
            ->paginate($perPage);
    }

    public function review(
        User $admin,
        int $id,
        array $data
    ): Prescription {
        return DB::transaction(function () use ($admin, $id, $data): Prescription {
            $prescription = Prescription::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($id);

            $status = $data['status'];

            if ($status === 'approved') {
                $sellerId = $data['seller_id'] ?? null;
                $storeId = $data['store_id'] ?? null;

                if (! $sellerId || ! $storeId) {
                    throw ValidationException::withMessages([
                        'seller_id' => 'Seller and store are required for approval.',
                    ]);
                }

                $store = Store::query()
                    ->where('seller_id', $sellerId)
                    ->findOrFail($storeId);

                $prescription->update([
                    'status' => 'approved',
                    'seller_id' => $sellerId,
                    'store_id' => $store->id,
                    'review_notes' => $data['review_notes'] ?? null,
                    'rejection_reason' => null,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                    'approved_at' => now(),
                    'expires_at' => $data['expires_at'] ?? now()->addDays(7),
                ]);
            } elseif ($status === 'rejected') {
                $prescription->update([
                    'status' => 'rejected',
                    'review_notes' => $data['review_notes'] ?? null,
                    'rejection_reason' => $data['rejection_reason'] ?? 'Prescription rejected.',
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);
            } else {
                $prescription->update([
                    'status' => 'under_review',
                    'review_notes' => $data['review_notes'] ?? null,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ]);
            }

            if ($prescription->user) {
                $this->notifications->notifyUser(
                    $prescription->user,
                    'Prescription status updated',
                    'Your prescription is now '.str_replace('_', ' ', $prescription->status).'.',
                    'prescription',
                    [
                        'prescription_uuid' => $prescription->uuid,
                        'status' => $prescription->status,
                    ]
                );
            }

            return $prescription->fresh([
                'items',
                'user',
                'seller',
                'store',
                'reviewer',
            ]);
        });
    }

    public function sellerPrescriptions(
        Seller $seller,
        int $perPage,
        ?string $status
    ): LengthAwarePaginator {
        return Prescription::query()
            ->where('seller_id', $seller->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->with(['items', 'user', 'store', 'reviewer', 'order'])
            ->latest()
            ->paginate($perPage);
    }

    public function fulfill(
        Seller $seller,
        User $sellerUser,
        int $id,
        ?string $note
    ): Prescription {
        $prescription = Prescription::query()
            ->where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->with('user')
            ->findOrFail($id);

        if ($prescription->expires_at && $prescription->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'prescription' => 'This prescription has expired.',
            ]);
        }

        $metadata = $prescription->metadata ?? [];
        $metadata['fulfillment_note'] = $note;
        $metadata['fulfilled_by'] = $sellerUser->id;

        $prescription->update([
            'status' => 'fulfilled',
            'fulfilled_at' => now(),
            'metadata' => $metadata,
        ]);

        if ($prescription->user) {
            $this->notifications->notifyUser(
                $prescription->user,
                'Prescription fulfilled',
                'The assigned pharmacy marked your prescription as fulfilled.',
                'prescription',
                ['prescription_uuid' => $prescription->uuid]
            );
        }

        return $prescription->fresh(['items', 'user', 'store', 'reviewer']);
    }
}
