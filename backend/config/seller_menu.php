<?php

return [
    'sections' => [
        [
            'title' => 'Overview',
            'items' => [
                ['title' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'seller.dashboard', 'active' => 'seller.dashboard'],
                ['title' => 'Orders', 'icon' => 'package', 'module' => 'orders', 'permission' => 'seller.orders.view'],
                ['title' => 'Return Requests', 'icon' => 'return', 'module' => 'returns', 'permission' => 'seller.returns.manage'],
                ['title' => 'POS', 'icon' => 'chart', 'module' => 'pos', 'permission' => 'seller.pos.manage'],
            ],
        ],
        [
            'title' => 'Catalogue',
            'items' => [
                ['title' => 'Stores', 'icon' => 'store', 'module' => 'stores', 'permission' => 'seller.stores.manage'],
                ['title' => 'Products', 'icon' => 'box', 'module' => 'products', 'permission' => 'seller.products.manage'],
                ['title' => 'Attributes', 'icon' => 'sparkles', 'module' => 'attributes', 'permission' => 'seller.attributes.manage'],
                ['title' => 'Add-ons', 'icon' => 'category', 'module' => 'addons', 'permission' => 'seller.addons.manage'],
                ['title' => 'Inventory', 'icon' => 'inventory', 'module' => 'inventory', 'permission' => 'seller.inventory.manage'],
            ],
        ],
        [
            'title' => 'Growth',
            'items' => [
                ['title' => 'Advertisements', 'icon' => 'ad', 'module' => 'advertisements', 'permission' => 'seller.ads.manage'],
                ['title' => 'Subscriptions', 'icon' => 'credit-card', 'module' => 'subscriptions', 'permission' => 'seller.subscriptions.manage'],
            ],
        ],
        [
            'title' => 'Finance',
            'items' => [
                ['title' => 'Wallet', 'icon' => 'wallet', 'module' => 'wallet', 'permission' => 'seller.finance.view'],
                ['title' => 'Statements', 'icon' => 'chart', 'module' => 'statements', 'permission' => 'seller.finance.view'],
                ['title' => 'Withdrawals', 'icon' => 'cash', 'module' => 'withdrawals', 'permission' => 'seller.withdrawals.manage'],
            ],
        ],
        [
            'title' => 'Engagement',
            'items' => [
                ['title' => 'Reviews', 'icon' => 'star', 'module' => 'reviews', 'permission' => 'seller.reviews.manage'],
                ['title' => 'Seller Feedback', 'icon' => 'support', 'module' => 'feedback', 'permission' => 'seller.feedback.manage'],
                ['title' => 'Prescriptions', 'icon' => 'prescription', 'module' => 'prescriptions', 'permission' => 'seller.prescriptions.manage'],
                ['title' => 'Product FAQs', 'icon' => 'faq', 'module' => 'product-faqs', 'permission' => 'seller.faqs.manage'],
                ['title' => 'Notifications', 'icon' => 'bell', 'module' => 'notifications', 'permission' => 'seller.notifications.view'],
            ],
        ],
        [
            'title' => 'Operations',
            'items' => [
                ['title' => 'Team & Roles', 'icon' => 'users', 'module' => 'team', 'permission' => 'seller.team.manage'],
                ['title' => 'Bulk Uploads', 'icon' => 'upload', 'module' => 'bulk-uploads', 'permission' => 'seller.bulk_uploads.manage'],
                ['title' => 'Profile & Settings', 'icon' => 'settings', 'route' => 'seller.profile.edit', 'active' => 'seller.profile.*'],
            ],
        ],
    ],
];
