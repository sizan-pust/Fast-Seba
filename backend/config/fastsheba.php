<?php

return [
    'version' => env('FASTSHEBA_VERSION', '1.0.0'),
    'currency' => env('FASTSHEBA_CURRENCY', 'BDT'),

    'pos' => [
        'parked_sale_days' => (int) env(
            'FASTSHEBA_POS_PARKED_DAYS',
            7
        ),
        'refund_days' => (int) env(
            'FASTSHEBA_POS_REFUND_DAYS',
            30
        ),
    ],

    'bulk_upload' => [
        'max_file_kb' => (int) env(
            'FASTSHEBA_BULK_MAX_KB',
            20480
        ),
        'process_limit' => (int) env(
            'FASTSHEBA_BULK_PROCESS_LIMIT',
            5
        ),
        'retention_days' => (int) env(
            'FASTSHEBA_BULK_RETENTION_DAYS',
            30
        ),
    ],

    'operations' => [
        'stuck_order_minutes' => (int) env(
            'FASTSHEBA_STUCK_ORDER_MINUTES',
            60
        ),
        'ad_dedup_days' => (int) env(
            'FASTSHEBA_AD_DEDUP_DAYS',
            7
        ),
    ],
];
