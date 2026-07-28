<?php

namespace App\Console\Commands;

use App\Services\NotificationInboxService;
use App\Services\ReferralService;
use Illuminate\Console\Command;

class SyncEngagementRecords extends Command
{
    protected $signature = 'engagement:sync';
    protected $description = 'Settle referral rewards and dispatch due notification campaigns';

    public function handle(
        ReferralService $referrals,
        NotificationInboxService $notifications
    ): int {
        $referralResult = $referrals->syncDeliveredOrders();
        $notificationResult = $notifications->dispatchScheduled();

        $this->info('Referral rewards settled: '.$referralResult['settled']);
        $this->info('Referral records skipped: '.$referralResult['skipped']);
        $this->info('Scheduled campaigns sent: '.$notificationResult['scheduled_sent']);

        return self::SUCCESS;
    }
}
