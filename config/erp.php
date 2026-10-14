<?php

/*
| ERP System configuration.
| `menu` drives both the sidebar and the Ctrl+K command palette, so a page
| only has to be registered here once. `can` is the permission needed to see it
| (null = every signed-in user). Admin user_type bypasses all permissions.
*/

return [

    'permissions' => [
        'customers.view' => 'View customers & enquiries',
        'customers.manage' => 'Create / edit customers & enquiries',
        'sales.manage' => 'Quotations, invoices & payments',
        'projects.view' => 'View projects',
        'projects.manage' => 'Create / edit projects, tasks, progress',
        'inventory.view' => 'View stock',
        'inventory.manage' => 'Stock in/out, purchase orders, suppliers',
        'service.manage' => 'Warranty, service requests, incidents',
        'costing.view' => 'Project costing & reports',
        'hr.manage' => 'Employees, attendance board, leave approval, holidays, announcements',
        'payroll.manage' => 'Payroll runs & payslips',
        'system.manage' => 'Users, roles, settings, audit log',
    ],

    // Default roles seeded on install (editable later in HR → Roles & Permissions).
    'roles' => [
        'Manager' => ['customers.view', 'customers.manage', 'sales.manage', 'projects.view', 'projects.manage', 'inventory.view', 'inventory.manage', 'service.manage', 'costing.view', 'hr.manage'],
        'Salesman' => ['customers.view', 'customers.manage', 'sales.manage', 'projects.view'],
        'Site Supervisor' => ['customers.view', 'projects.view', 'projects.manage', 'inventory.view', 'service.manage'],
        'Storekeeper' => ['inventory.view', 'inventory.manage', 'projects.view'],
        'HR & Admin' => ['hr.manage', 'payroll.manage'],
        'Accounts' => ['customers.view', 'sales.manage', 'projects.view', 'costing.view', 'inventory.view'],
        'Staff' => [],
    ],

    'services' => [
        'Roof Leaking & Repair', 'Roof Replacement', 'Flat Roof Waterproofing', 'Roof Coating',
        'V-Bond Roof Tiles', 'Metal Roofing', 'Torch-on Membrane', 'Gutter & Downpipe',
        'Skylight & Polycarbonate', 'Ceiling & Insulation', 'Wall Waterproofing',
    ],

    'states' => ['Kuala Lumpur', 'Selangor', 'Negeri Sembilan', 'Melaka', 'Johor', 'Perak', 'Pulau Pinang', 'Kedah', 'Pahang', 'Sabah', 'Sarawak'],

    'customer_sources' => ['Website', 'WhatsApp', 'Facebook', 'Xiaohongshu', 'Referral', 'Walk-in', 'Repeat Customer'],

    'item_categories' => ['Roofing Sheet', 'Roof Tiles', 'Membrane', 'Coating', 'Accessories', 'Gutter', 'Safety', 'Tools'],

    'uoms' => ['PCS', 'SHEET', 'ROLL', 'PAIL', 'BOX', 'SET', 'M', 'SQFT', 'KG', 'LOT'],

    'payment_methods' => ['Bank Transfer', 'DuitNow', 'Cash', 'Cheque', 'Card'],

    'menu' => [
        ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard'],

        ['group' => 'Customer', 'icon' => 'users', 'items' => [
            ['label' => 'Customer Profile', 'route' => 'customers.index', 'can' => 'customers.view'],
            ['label' => 'Enquiries / Pipeline', 'route' => 'enquiries.index', 'can' => 'customers.view'],
            ['label' => 'Customer Report', 'route' => 'reports.customers', 'can' => 'customers.view'],
            ['label' => 'Salesman Report', 'route' => 'reports.salesman', 'can' => 'customers.view'],
        ]],

        ['group' => 'Sales', 'icon' => 'receipt', 'items' => [
            ['label' => 'Quotations', 'route' => 'quotations.index', 'can' => 'sales.manage'],
            ['label' => 'Invoices & Proforma', 'route' => 'invoices.index', 'can' => 'sales.manage'],
            ['label' => 'Payments Received', 'route' => 'payments.index', 'can' => 'sales.manage'],
        ]],

        ['group' => 'Project', 'icon' => 'briefcase', 'items' => [
            ['label' => 'Project List', 'route' => 'projects.index', 'can' => 'projects.view'],
            ['label' => 'Project Board', 'route' => 'projects.index', 'params' => ['view' => 'board'], 'can' => 'projects.view'],
            ['label' => 'Project Calendar', 'route' => 'projects.calendar', 'can' => 'projects.view'],
            ['label' => 'Project Schedule', 'route' => 'projects.schedule', 'can' => 'projects.view'],
        ]],

        ['group' => 'Inventory', 'icon' => 'cube', 'items' => [
            ['label' => 'Stock Master', 'route' => 'items.index', 'can' => 'inventory.view'],
            ['label' => 'Stock Transactions', 'route' => 'stock.index', 'can' => 'inventory.view'],
            ['label' => 'Purchase Orders', 'route' => 'purchase-orders.index', 'can' => 'inventory.manage'],
            ['label' => 'Suppliers', 'route' => 'suppliers.index', 'can' => 'inventory.view'],
            ['label' => 'Warehouses', 'route' => 'warehouses.index', 'can' => 'inventory.manage'],
            ['label' => 'Worker Rates', 'route' => 'worker-rates.index', 'can' => 'inventory.manage'],
        ]],

        ['group' => 'After-sales', 'icon' => 'shield', 'items' => [
            ['label' => 'Warranty', 'route' => 'warranties.index', 'can' => 'service.manage'],
            ['label' => 'Service Requests', 'route' => 'service-requests.index', 'can' => 'service.manage'],
            ['label' => 'Appointment Calendar', 'route' => 'service-requests.calendar', 'can' => 'service.manage'],
            ['label' => 'Incident Report', 'route' => 'incidents.index', 'can' => 'service.manage'],
        ]],

        ['group' => 'HR', 'icon' => 'identification', 'items' => [
            ['label' => 'My Attendance', 'route' => 'attendance.index'],
            ['label' => 'My Leave', 'route' => 'leave.index'],
            ['label' => 'Employees', 'route' => 'employees.index', 'can' => 'hr.manage'],
            ['label' => 'Attendance Board', 'route' => 'attendance.board', 'can' => 'hr.manage'],
            ['label' => 'Attendance Report', 'route' => 'attendance.report', 'can' => 'hr.manage'],
            ['label' => 'Leave Approval', 'route' => 'leave.approvals', 'can' => 'hr.manage'],
            ['label' => 'Payroll', 'route' => 'payroll.index', 'can' => 'payroll.manage'],
            ['label' => 'Holidays', 'route' => 'holidays.index', 'can' => 'hr.manage'],
            ['label' => 'Announcements', 'route' => 'announcements.index', 'can' => 'hr.manage'],
        ]],

        ['group' => 'Costing', 'icon' => 'chart', 'items' => [
            ['label' => 'Project Cost Management', 'route' => 'costing.projects', 'can' => 'costing.view'],
            ['label' => 'Costing Summary Report', 'route' => 'costing.summary', 'can' => 'costing.view'],
        ]],

        ['group' => 'System', 'icon' => 'cog', 'items' => [
            ['label' => 'Users', 'route' => 'users.index', 'can' => 'system.manage'],
            ['label' => 'Roles & Permissions', 'route' => 'roles.index', 'can' => 'system.manage'],
            ['label' => 'Company Settings', 'route' => 'settings.index', 'can' => 'system.manage'],
            ['label' => 'Audit Log', 'route' => 'audit.index', 'can' => 'system.manage'],
            ['label' => 'Login Activity', 'route' => 'audit.logins', 'can' => 'system.manage'],
        ]],
    ],
];
