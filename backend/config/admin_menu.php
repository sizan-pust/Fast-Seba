<?php

return [
    'sections' => [
        [
            'title' => 'Overview',
            'items' => [
                ['title' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'live' => true],
                ['title' => 'POS Dashboard', 'icon' => 'chart', 'module' => 'pos-dashboard', 'permission' => 'orders.view', 'live' => true, 'group' => 'system'],
                ['title' => 'Orders', 'icon' => 'package', 'module' => 'orders', 'permission' => 'orders.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Return Requests', 'icon' => 'return', 'module' => 'returns', 'permission' => 'returns.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Dispatch Management', 'icon' => 'truck', 'module' => 'dispatch', 'permission' => 'orders.manage', 'live' => true, 'group' => 'core'],
            ],
        ],
        [
            'title' => 'Catalog',
            'items' => [
                ['title' => 'Categories', 'icon' => 'category', 'module' => 'categories', 'permission' => 'categories.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Products', 'icon' => 'box', 'module' => 'products', 'permission' => 'products.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Brands', 'icon' => 'sparkles', 'module' => 'brands', 'permission' => 'brands.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Inventory', 'icon' => 'inventory', 'module' => 'inventory', 'permission' => 'inventory.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Tax Classes', 'icon' => 'percentage', 'module' => 'tax-classes', 'permission' => 'tax_classes.view', 'live' => true, 'group' => 'core'],
            ],
        ],
        [
            'title' => 'People',
            'items' => [
                ['title' => 'Customers', 'icon' => 'users', 'module' => 'customers', 'permission' => 'customers.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Seller Management', 'icon' => 'seller', 'module' => 'sellers', 'permission' => 'sellers.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Stores', 'icon' => 'store', 'module' => 'stores', 'permission' => 'stores.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Manage Delivery Partners', 'icon' => 'truck', 'module' => 'delivery-partners', 'permission' => 'delivery_boys.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Delivery Zones', 'icon' => 'map', 'module' => 'delivery-zones', 'permission' => 'delivery_zones.view', 'live' => true, 'group' => 'core'],
            ],
        ],
        [
            'title' => 'Marketing',
            'items' => [
                ['title' => 'Banners', 'icon' => 'photo', 'module' => 'banners', 'permission' => 'banners.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Featured Sections', 'icon' => 'layout', 'module' => 'featured-sections', 'permission' => 'featured_sections.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Promotions', 'icon' => 'ticket', 'module' => 'promotions', 'permission' => 'promos.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Advertisements', 'icon' => 'ad', 'module' => 'advertisements', 'permission' => 'ads.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Subscriptions', 'icon' => 'credit-card', 'module' => 'subscriptions', 'permission' => 'subscriptions.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Gift Cards & Referrals', 'icon' => 'gift', 'module' => 'gift-cards-referrals', 'permission' => 'gift_cards.view', 'live' => true, 'group' => 'manage'],
            ],
        ],
        [
            'title' => 'Finance',
            'items' => [
                ['title' => 'Seller Statements', 'icon' => 'chart', 'module' => 'seller-statements', 'permission' => 'seller_finance.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Seller Withdrawals', 'icon' => 'wallet', 'module' => 'seller-withdrawals', 'permission' => 'seller_withdrawals.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Rider Cash Settlement', 'icon' => 'cash', 'module' => 'rider-cash', 'permission' => 'delivery_cash.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Payment Gateways', 'icon' => 'credit-card', 'module' => 'payment-gateways', 'permission' => 'payment_gateways.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Payment Intents', 'icon' => 'activity', 'module' => 'payment-intents', 'permission' => 'payments.view', 'live' => true, 'group' => 'manage'],
            ],
        ],
        [
            'title' => 'Communication',
            'items' => [
                ['title' => 'Prescriptions', 'icon' => 'prescription', 'module' => 'prescriptions', 'permission' => 'prescriptions.view', 'live' => true, 'group' => 'core'],
                ['title' => 'Support Tickets', 'icon' => 'support', 'module' => 'support', 'permission' => 'support.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Reviews', 'icon' => 'star', 'module' => 'reviews', 'permission' => 'reviews.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'Notifications', 'icon' => 'bell', 'module' => 'notifications', 'permission' => 'notifications.view', 'live' => true, 'group' => 'manage'],
                ['title' => 'FAQs', 'icon' => 'faq', 'module' => 'faqs', 'permission' => 'faqs.view', 'live' => true, 'group' => 'manage'],
            ],
        ],
        [
            'title' => 'System',
            'items' => [
                ['title' => 'Roles & Users', 'icon' => 'shield', 'module' => 'roles-users', 'permission' => 'roles.view', 'live' => true, 'group' => 'system'],
                ['title' => 'Settings', 'icon' => 'settings', 'module' => 'settings', 'permission' => 'settings.manage', 'live' => true, 'group' => 'system'],
                ['title' => 'Bulk Uploads', 'icon' => 'upload', 'module' => 'bulk-uploads', 'permission' => 'bulk_uploads.view', 'live' => true, 'group' => 'system'],
                ['title' => 'Audit Logs', 'icon' => 'activity', 'module' => 'audit-logs', 'permission' => 'audit_logs.view', 'live' => true, 'group' => 'system'],
                ['title' => 'System Operations', 'icon' => 'server', 'module' => 'system-operations', 'permission' => 'system.manage', 'live' => true, 'group' => 'system'],
            ],
        ],
    ],
];
