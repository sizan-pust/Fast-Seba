<?php

return [
    'sections' => [
        [
            'title' => 'Overview',
            'items' => [
                [
                    'title' => 'Dashboard',
                    'icon' => 'dashboard',
                    'route' => 'admin.dashboard',
                    'active' => 'admin.dashboard',
                ],
                [
                    'title' => 'Orders',
                    'icon' => 'package',
                    'module' => 'orders',
                    'permission' => 'orders.view',
                ],
                [
                    'title' => 'Return requests',
                    'icon' => 'return',
                    'module' => 'returns',
                    'permission' => 'returns.view',
                ],
                [
                    'title' => 'Dispatch management',
                    'icon' => 'truck',
                    'module' => 'dispatch',
                    'permission' => 'orders.manage',
                ],
            ],
        ],
        [
            'title' => 'Catalog',
            'items' => [
                [
                    'title' => 'Categories',
                    'icon' => 'category',
                    'module' => 'categories',
                    'permission' => 'categories.view',
                ],
                [
                    'title' => 'Products',
                    'icon' => 'box',
                    'module' => 'products',
                    'permission' => 'products.view',
                ],
                [
                    'title' => 'Brands',
                    'icon' => 'sparkles',
                    'module' => 'brands',
                    'permission' => 'brands.view',
                ],
                [
                    'title' => 'Inventory',
                    'icon' => 'inventory',
                    'module' => 'inventory',
                    'permission' => 'inventory.view',
                ],
                [
                    'title' => 'Tax classes',
                    'icon' => 'percentage',
                    'module' => 'tax-classes',
                    'permission' => 'tax_classes.view',
                ],
            ],
        ],
        [
            'title' => 'People',
            'items' => [
                [
                    'title' => 'Customers',
                    'icon' => 'users',
                    'module' => 'customers',
                    'permission' => 'customers.view',
                ],
                [
                    'title' => 'Sellers',
                    'icon' => 'seller',
                    'module' => 'sellers',
                    'permission' => 'sellers.view',
                ],
                [
                    'title' => 'Stores',
                    'icon' => 'store',
                    'module' => 'stores',
                    'permission' => 'stores.view',
                ],
                [
                    'title' => 'Delivery partners',
                    'icon' => 'truck',
                    'module' => 'delivery-partners',
                    'permission' => 'delivery_boys.view',
                ],
                [
                    'title' => 'Delivery zones',
                    'icon' => 'map',
                    'module' => 'delivery-zones',
                    'permission' => 'delivery_zones.view',
                ],
            ],
        ],
        [
            'title' => 'Marketing',
            'items' => [
                [
                    'title' => 'Banners',
                    'icon' => 'photo',
                    'module' => 'banners',
                    'permission' => 'banners.view',
                ],
                [
                    'title' => 'Featured sections',
                    'icon' => 'layout',
                    'module' => 'featured-sections',
                    'permission' => 'featured_sections.view',
                ],
                [
                    'title' => 'Promotions',
                    'icon' => 'ticket',
                    'module' => 'promotions',
                    'permission' => 'promos.view',
                ],
                [
                    'title' => 'Advertisements',
                    'icon' => 'ad',
                    'module' => 'advertisements',
                    'permission' => 'ads.view',
                ],
                [
                    'title' => 'Subscriptions',
                    'icon' => 'credit-card',
                    'module' => 'subscriptions',
                    'permission' => 'subscriptions.view',
                ],
                [
                    'title' => 'Gift cards & referrals',
                    'icon' => 'gift',
                    'module' => 'gift-cards-referrals',
                    'permission' => 'gift_cards.view',
                ],
            ],
        ],
        [
            'title' => 'Finance',
            'items' => [
                [
                    'title' => 'Seller statements',
                    'icon' => 'chart',
                    'module' => 'seller-statements',
                    'permission' => 'seller_finance.view',
                ],
                [
                    'title' => 'Seller withdrawals',
                    'icon' => 'wallet',
                    'module' => 'seller-withdrawals',
                    'permission' => 'seller_withdrawals.view',
                ],
                [
                    'title' => 'Rider cash settlement',
                    'icon' => 'cash',
                    'module' => 'rider-cash',
                    'permission' => 'delivery_cash.view',
                ],
                [
                    'title' => 'Payment gateways',
                    'icon' => 'credit-card',
                    'module' => 'payment-gateways',
                    'permission' => 'payment_gateways.view',
                ],
                [
                    'title' => 'Payment intents',
                    'icon' => 'activity',
                    'module' => 'payment-intents',
                    'permission' => 'payments.view',
                ],
            ],
        ],
        [
            'title' => 'Communication',
            'items' => [
                [
                    'title' => 'Prescriptions',
                    'icon' => 'prescription',
                    'module' => 'prescriptions',
                    'permission' => 'prescriptions.view',
                ],
                [
                    'title' => 'Support tickets',
                    'icon' => 'support',
                    'module' => 'support',
                    'permission' => 'support.view',
                ],
                [
                    'title' => 'Reviews',
                    'icon' => 'star',
                    'module' => 'reviews',
                    'permission' => 'reviews.view',
                ],
                [
                    'title' => 'Notifications',
                    'icon' => 'bell',
                    'module' => 'notifications',
                    'permission' => 'notifications.view',
                ],
                [
                    'title' => 'FAQs',
                    'icon' => 'faq',
                    'module' => 'faqs',
                    'permission' => 'faqs.view',
                ],
            ],
        ],
        [
            'title' => 'System',
            'items' => [
                [
                    'title' => 'Roles & users',
                    'icon' => 'shield',
                    'module' => 'roles-users',
                    'permission' => 'roles.view',
                ],
                [
                    'title' => 'Settings',
                    'icon' => 'settings',
                    'module' => 'settings',
                    'permission' => 'settings.manage',
                ],
                [
                    'title' => 'Bulk uploads',
                    'icon' => 'upload',
                    'module' => 'bulk-uploads',
                    'permission' => 'bulk_uploads.view',
                ],
                [
                    'title' => 'Audit logs',
                    'icon' => 'activity',
                    'module' => 'audit-logs',
                    'permission' => 'audit_logs.view',
                ],
                [
                    'title' => 'System operations',
                    'icon' => 'server',
                    'module' => 'system-operations',
                    'permission' => 'system.manage',
                ],
            ],
        ],
    ],
];
