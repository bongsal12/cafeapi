<?php

return [
    'labels' => [
        'admin' => 'Admin',
        'staff' => 'Cashier',
        'inventory_staff' => 'Inventory Staff',
    ],

    'permissions' => [
        'dashboard' => 'Dashboard',
        'orders' => 'Orders',
        'pos' => 'POS',
        'products' => 'Products',
        'inventory' => 'Inventory',
        'users' => 'Users',
        'reports' => 'Reports',
        'tables' => 'QR Tables',
        'discounts' => 'Discounts',
        'settings' => 'Role Access',
    ],

    'defaults' => [
        'admin' => ['dashboard', 'orders', 'pos', 'products', 'inventory', 'users', 'reports', 'tables', 'discounts', 'settings'],
        'staff' => ['orders', 'pos'],
        'inventory_staff' => ['inventory'],
    ],
];
