<?php

return [
    'sections' => [
        [
            'title' => 'Overview',
            'items' => [
                ['title' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'live' => true],
                ['title' => 'POS Dashboard', 'icon' => 'chart', 'module' => 'pos-dashboard', 'permission' => 'orders.view', 'live' => false],
                ['title' => 'Orders', 'icon' => 'package', 'module' => 'orders', 'permission' => 'orders.view', 'live' => true],
                ['title' => 'Return Requests', 'icon' => 'return', 'module' => 'returns', 'permission' => 'returns.view', 'live' => true],
                ['title' => 'Dispatch Management', 'icon' => 'truck', 'module' => 'dispatch', 'permission' => 'orders.manage', 'live' => true],
            ],
        ],
        [
            'title' => 'Catalog',
            'items' => [
                ['title' => 'Categories', 'icon' => 'category', 'module' => 'categories', 'permission' => 'categories.view', 'live' => true],
                ['title' => 'Products', 'icon' => 'box', 'module' => 'products', 'permission' => 'products.view', 'live' => true],
                ['title' => 'Brands', 'icon' => 'sparkles', 'module' => 'brands', 'permission' => 'brands.view', 'live' => true],
                ['title' => 'Inventory', 'icon' => 'inventory', 'module' => 'inventory', 'permission' => 'inventory.view', 'live' => true],
                ['title' => 'Tax Classes', 'icon' => 'percentage', 'module' => 'tax-classes', 'permission' => 'tax_classes.view', 'live' => true],
            ],
        ],
        [
            'title' => 'People',
            'items' => [
                ['title' => 'Customers', 'icon' => 'users', 'module' => 'customers', 'permission' => 'customers.view', 'live' => true],
                ['title' => 'Seller Management', 'icon' => 'seller', 'module' => 'sellers', 'permission' => 'sellers.view', 'live' => true],
                ['title' => 'Stores', 'icon' => 'store', 'module' => 'stores', 'permission' => 'stores.view', 'live' => true],
                ['title' => 'Manage Delivery Partners', 'icon' => 'truck', 'module' => 'delivery-partners', 'permission' => 'delivery_boys.view', 'live' => true],
                ['title' => 'Delivery Zones', 'icon' => 'map', 'module' => 'delivery-zones', 'permission' => 'delivery_zones.view', 'live' => true],
            ],
        ],
        [
            'title' => 'Marketing',
            'items' => [
                ['title' => 'Banners', 'icon' => 'photo', 'module' => 'banners', 'permission' => 'banners.view', 'live' => false],
                ['title' => 'Featured Sections', 'icon' => 'layout', 'module' => 'featured-sections', 'permission' => 'featured_sections.view', 'live' => false],
                ['title' => 'Promotions', 'icon' => 'ticket', 'module' => 'promotions', 'permission' => 'promos.view', 'live' => false],
                ['title' => 'Advertisements', 'icon' => 'ad', 'module' => 'advertisements', 'permission' => 'ads.view', 'live' => false],
                ['title' => 'Subscriptions', 'icon' => 'credit-card', 'module' => 'subscriptions', 'permission' => 'subscriptions.view', 'live' => false],
                ['title' => 'Gift Cards & Referrals', 'icon' => 'gift', 'module' => 'gift-cards-referrals', 'permission' => 'gift_cards.view', 'live' => false],
            ],
        ],
        [
            'title' => 'Finance',
            'items' => [
                ['title' => 'Seller Statements', 'icon' => 'chart', 'module' => 'seller-statements', 'permission' => 'seller_finance.view', 'live' => false],
                ['title' => 'Seller Withdrawals', 'icon' => 'wallet', 'module' => 'seller-withdrawals', 'permission' => 'seller_withdrawals.view', 'live' => false],
                ['title' => 'Rider Cash Settlement', 'icon' => 'cash', 'module' => 'rider-cash', 'permission' => 'delivery_cash.view', 'live' => false],
                ['title' => 'Payment Gateways', 'icon' => 'credit-card', 'module' => 'payment-gateways', 'permission' => 'payment_gateways.view', 'live' => false],
                ['title' => 'Payment Intents', 'icon' => 'activity', 'module' => 'payment-intents', 'permission' => 'payments.view', 'live' => false],
            ],
        ],
        [
            'title' => 'Communication',
            'items' => [
                ['title' => 'Prescriptions', 'icon' => 'prescription', 'module' => 'prescriptions', 'permission' => 'prescriptions.view', 'live' => true],
                ['title' => 'Support Tickets', 'icon' => 'support', 'module' => 'support', 'permission' => 'support.view', 'live' => false],
                ['title' => 'Reviews', 'icon' => 'star', 'module' => 'reviews', 'permission' => 'reviews.view', 'live' => false],
                ['title' => 'Notifications', 'icon' => 'bell', 'module' => 'notifications', 'permission' => 'notifications.view', 'live' => false],
                ['title' => 'FAQs', 'icon' => 'faq', 'module' => 'faqs', 'permission' => 'faqs.view', 'live' => false],
            ],
        ],
        [
            'title' => 'System',
            'items' => [
                ['title' => 'Roles & Users', 'icon' => 'shield', 'module' => 'roles-users', 'permission' => 'roles.view', 'live' => false],
                ['title' => 'Settings', 'icon' => 'settings', 'module' => 'settings', 'permission' => 'settings.manage', 'live' => false],
                ['title' => 'Bulk Uploads', 'icon' => 'upload', 'module' => 'bulk-uploads', 'permission' => 'bulk_uploads.view', 'live' => false],
                ['title' => 'Audit Logs', 'icon' => 'activity', 'module' => 'audit-logs', 'permission' => 'audit_logs.view', 'live' => false],
                ['title' => 'System Operations', 'icon' => 'server', 'module' => 'system-operations', 'permission' => 'system.manage', 'live' => false],
            ],
        ],
    ],
];
