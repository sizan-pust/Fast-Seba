<?php

namespace App\Console\Commands;

use App\Services\SellerFinanceService;
use Illuminate\Console\Command;

class SyncFinanceRecords extends Command
{
    protected $signature = 'finance:sync';
    protected $description = 'Create seller statements and credit delivered rider earnings';

    public function handle(SellerFinanceService $finance): int
    {
        $result = $finance->sync();

        $this->info('Seller credits: '.$result['sellerCredits']);
        $this->info('Seller debits: '.$result['sellerDebits']);
        $this->info('Rider credits: '.$result['riderCredits']);

        return self::SUCCESS;
    }
}
