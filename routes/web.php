<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CostingController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectCostController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarrantyController;
use App\Http\Controllers\WorkerRateController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::post('/locale/{locale}', [ProfileController::class, 'locale'])->name('locale');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->name('profile.password');

    // ---------- Self-service (every employee) ----------
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('attendance/clock', [AttendanceController::class, 'clock'])->name('attendance.clock');
    Route::get('leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::post('leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::post('leave/{leaveRequest}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');
    Route::get('payslips/{payslip}', [PayrollController::class, 'payslip'])->name('payslips.show'); // own payslip or payroll.manage

    // ---------- Customer ----------
    Route::middleware('can:customers.view')->group(function () {
        Route::get('customers/export', [CustomerController::class, 'export'])->name('customers.export');
        Route::get('enquiries/export', [EnquiryController::class, 'export'])->name('enquiries.export');
        Route::get('reports/customers', [CustomerReportController::class, 'customers'])->name('reports.customers');
        Route::get('reports/salesman', [CustomerReportController::class, 'salesman'])->name('reports.salesman');
    });
    Route::resource('customers', CustomerController::class)->except('index', 'show')->middleware('can:customers.manage');
    Route::resource('customers', CustomerController::class)->only('index', 'show')->middleware('can:customers.view');
    Route::patch('enquiries/{enquiry}/stage', [EnquiryController::class, 'stage'])->name('enquiries.stage')->middleware('can:customers.manage');
    Route::resource('enquiries', EnquiryController::class)->except('index', 'show')->middleware('can:customers.manage');
    Route::resource('enquiries', EnquiryController::class)->only('index', 'show')->middleware('can:customers.view');

    // ---------- Sales documents ----------
    Route::middleware('can:sales.manage')->group(function () {
        Route::get('quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
        Route::post('quotations/{quotation}/status', [QuotationController::class, 'status'])->name('quotations.status');
        Route::post('quotations/{quotation}/invoice', [QuotationController::class, 'toInvoice'])->name('quotations.invoice');
        Route::post('quotations/{quotation}/project', [QuotationController::class, 'toProject'])->name('quotations.project');
        Route::resource('quotations', QuotationController::class);
        Route::get('invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
        Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
        Route::resource('invoices', InvoiceController::class);
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('invoices/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->name('payments.destroy');
    });

    // ---------- Project ----------
    Route::middleware('can:projects.view')->group(function () {
        Route::get('projects/calendar', [ProjectController::class, 'calendar'])->name('projects.calendar');
        Route::get('projects/schedule', [ProjectController::class, 'schedule'])->name('projects.schedule');
    });
    Route::middleware('can:projects.manage')->group(function () {
        Route::patch('projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
        Route::post('projects/{project}/updates', [ProjectController::class, 'addUpdate'])->name('projects.updates.store');
        Route::post('projects/{project}/tasks', [ProjectTaskController::class, 'store'])->name('project-tasks.store');
        Route::patch('project-tasks/{task}', [ProjectTaskController::class, 'update'])->name('project-tasks.update');
        Route::delete('project-tasks/{task}', [ProjectTaskController::class, 'destroy'])->name('project-tasks.destroy');
        Route::post('projects/{project}/costs', [ProjectCostController::class, 'store'])->name('project-costs.store');
        Route::delete('project-costs/{cost}', [ProjectCostController::class, 'destroy'])->name('project-costs.destroy');
    });
    Route::resource('projects', ProjectController::class)->except('index', 'show')->middleware('can:projects.manage');
    Route::resource('projects', ProjectController::class)->only('index', 'show')->middleware('can:projects.view');

    // ---------- Inventory ----------
    Route::middleware('can:inventory.view')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/export', [StockController::class, 'export'])->name('stock.export');
    });
    Route::middleware('can:inventory.manage')->group(function () {
        Route::post('stock', [StockController::class, 'store'])->name('stock.store');
        Route::post('stock/transfer', [StockController::class, 'transfer'])->name('stock.transfer');
        Route::resource('warehouses', WarehouseController::class)->except('show', 'create', 'edit');
        Route::resource('worker-rates', WorkerRateController::class)->except('show', 'create', 'edit');
        Route::post('suppliers/{supplier}/prices', [SupplierController::class, 'addPrice'])->name('suppliers.prices.store');
        Route::post('purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'status'])->name('purchase-orders.status');
        Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::resource('purchase-orders', PurchaseOrderController::class);
    });
    Route::resource('items', ItemController::class)->except('index', 'show')->middleware('can:inventory.manage');
    Route::resource('items', ItemController::class)->only('index', 'show')->middleware('can:inventory.view');
    Route::resource('suppliers', SupplierController::class)->except('index', 'show')->middleware('can:inventory.manage');
    Route::resource('suppliers', SupplierController::class)->only('index', 'show')->middleware('can:inventory.view');

    // ---------- After-sales ----------
    Route::middleware('can:service.manage')->group(function () {
        Route::get('warranties/{warranty}/print', [WarrantyController::class, 'print'])->name('warranties.print');
        Route::resource('warranties', WarrantyController::class);
        Route::get('service-requests/calendar', [ServiceRequestController::class, 'calendar'])->name('service-requests.calendar');
        Route::patch('service-requests/{serviceRequest}/status', [ServiceRequestController::class, 'status'])->name('service-requests.status');
        Route::resource('service-requests', ServiceRequestController::class);
        Route::resource('incidents', IncidentController::class);
    });

    // ---------- HR ----------
    Route::middleware('can:hr.manage')->group(function () {
        Route::resource('employees', EmployeeController::class);
        Route::get('attendance/board', [AttendanceController::class, 'board'])->name('attendance.board');
        Route::get('attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
        Route::post('attendance/manual', [AttendanceController::class, 'manual'])->name('attendance.manual');
        Route::get('leave/approvals', [LeaveController::class, 'approvals'])->name('leave.approvals');
        Route::post('leave/{leaveRequest}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
        Route::post('leave/{leaveRequest}/reject', [LeaveController::class, 'reject'])->name('leave.reject');
        Route::resource('holidays', HolidayController::class)->only('index', 'store', 'destroy');
        Route::resource('announcements', AnnouncementController::class)->except('show', 'create', 'edit');
    });
    Route::middleware('can:payroll.manage')->group(function () {
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('payroll/{payrollRun}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('payroll/{payrollRun}/export', [PayrollController::class, 'export'])->name('payroll.export');
        Route::post('payroll/{payrollRun}/regenerate', [PayrollController::class, 'regenerate'])->name('payroll.regenerate');
        Route::post('payroll/{payrollRun}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
        Route::put('payslips/{payslip}', [PayrollController::class, 'updatePayslip'])->name('payslips.update');
    });

    // ---------- Costing ----------
    Route::middleware('can:costing.view')->group(function () {
        Route::get('costing/projects', [CostingController::class, 'projects'])->name('costing.projects');
        Route::get('costing/summary', [CostingController::class, 'summary'])->name('costing.summary');
        Route::get('costing/summary/export', [CostingController::class, 'export'])->name('costing.export');
    });

    // ---------- System ----------
    Route::middleware('can:system.manage')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::resource('roles', RoleController::class)->except('show', 'create', 'edit');
        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/{kind}', [SettingController::class, 'storeLookup'])->name('settings.lookup.store')->whereIn('kind', ['branches', 'departments', 'leave-types']);
        Route::delete('settings/{kind}/{id}', [SettingController::class, 'destroyLookup'])->name('settings.lookup.destroy')->whereIn('kind', ['branches', 'departments', 'leave-types']);
        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('audit/logins', [AuditController::class, 'logins'])->name('audit.logins');
    });
});
