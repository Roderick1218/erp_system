<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERP System core schema — modules:
 * Customer / Project / Inventory / Warranty / HR / Others / Costing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('reg_no', 64)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_no', 32)->unique();
            $table->string('name');
            $table->string('nickname', 64)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->foreignId('department_id')->nullable()->constrained();
            $table->string('position', 100)->nullable();
            $table->string('employment_type', 32)->default('permanent'); // permanent|contract|foreign_worker|intern
            $table->string('gender', 8)->nullable();
            $table->date('dob')->nullable();
            $table->string('nationality', 64)->default('MALAYSIA');
            $table->string('ic_no', 32)->nullable();
            $table->string('passport_no', 32)->nullable();
            $table->date('passport_expiry')->nullable();
            $table->string('work_permit_no', 64)->nullable();
            $table->date('work_permit_expiry')->nullable();
            $table->string('cidb_no', 64)->nullable();
            $table->date('cidb_expiry')->nullable();
            $table->string('gdl_no', 64)->nullable();
            $table->date('gdl_expiry')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('fixed_allowance', 12, 2)->default(0);
            $table->string('epf_no', 32)->nullable();
            $table->string('socso_no', 32)->nullable();
            $table->string('tax_no', 32)->nullable();
            $table->string('bank_name', 64)->nullable();
            $table->string('bank_account', 64)->nullable();
            $table->string('status', 16)->default('active'); // active|resigned|suspended
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type', 16)->default('staff')->after('email'); // admin|manager|staff
            $table->foreignId('employee_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('login_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('event', 16); // login|logout|failed
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained(); // null = all branches
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('published_on');
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ---------------- HR: attendance / leave / payroll ----------------
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->string('source', 16)->default('web'); // web|mobile|manual
            $table->boolean('is_late')->default(false);
            $table->string('remarks')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 64);
            $table->decimal('days_per_year', 5, 1)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->string('color', 16)->default('#2b7bb9');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 5, 1);
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('pending'); // pending|approved|rejected|cancelled
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // YYYY-MM
            $table->string('status', 16)->default('draft'); // draft|finalized
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained();
            $table->decimal('basic', 12, 2)->default(0);
            $table->decimal('allowance', 12, 2)->default(0);
            $table->decimal('overtime', 12, 2)->default(0);
            $table->decimal('commission', 12, 2)->default(0);
            $table->decimal('unpaid_leave', 12, 2)->default(0);
            $table->decimal('other_deduction', 12, 2)->default(0);
            $table->decimal('gross', 12, 2)->default(0);
            $table->decimal('epf_employee', 12, 2)->default(0);
            $table->decimal('epf_employer', 12, 2)->default(0);
            $table->decimal('socso_employee', 12, 2)->default(0);
            $table->decimal('socso_employer', 12, 2)->default(0);
            $table->decimal('eis_employee', 12, 2)->default(0);
            $table->decimal('eis_employer', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        // ---------------- Customer / CRM ----------------
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->string('type', 16)->default('individual'); // individual|company|government
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('phone2', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('document_type', 16)->nullable(); // IC|BRN|PASSPORT
            $table->string('document_id', 64)->nullable();
            $table->text('address')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city', 64)->nullable();
            $table->string('state', 64)->nullable();
            $table->string('source', 32)->nullable(); // Website|WhatsApp|Referral|Walk-in|Facebook|Xiaohongshu
            $table->string('status', 16)->default('active'); // active|inactive|blacklist
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->date('enquiry_date');
            $table->string('contact_person')->nullable();
            $table->string('contact_no', 32)->nullable();
            $table->string('service', 64);
            $table->text('site_address')->nullable();
            $table->string('state', 64)->nullable();
            $table->date('inspection_date')->nullable();
            $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage', 16)->default('new'); // new|inspection|quotation|negotiation|won|lost
            $table->text('feedback')->nullable();
            $table->decimal('estimate_amount', 14, 2)->nullable();
            $table->string('lost_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ---------------- Inventory ----------------
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name');
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('payment_terms', 32)->nullable(); // COD|30 days|60 days
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->string('category', 64)->nullable(); // Roofing Sheet|Membrane|Coating|Accessories|Safety|Tools
            $table->string('uom', 16)->default('PCS');
            $table->decimal('unit_cost', 14, 4)->default(0); // moving average
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('reorder_level', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 14, 2)->default(0);
            $table->unique(['item_id', 'warehouse_id']);
        });

        Schema::create('supplier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->decimal('unit_price', 14, 4);
            $table->date('effective_date');
            $table->timestamps();
        });

        Schema::create('worker_rates', function (Blueprint $table) {
            $table->id();
            $table->string('trade', 64); // Roofer|Waterproofer|Helper|Welder|Supervisor
            $table->string('description')->nullable();
            $table->string('rate_type', 16)->default('day'); // day|hour|sqft|job
            $table->decimal('rate', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ---------------- Project ----------------
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique(); // PR-2026/0001
            $table->string('name');
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 16)->default('normal'); // normal|temporary|aftercare
            $table->string('service', 64)->nullable();
            $table->text('site_address')->nullable();
            $table->string('state', 64)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 16)->default('planning'); // planning|scheduled|in_progress|on_hold|completed|cancelled
            $table->unsignedTinyInteger('progress')->default(0);
            $table->decimal('contract_value', 14, 2)->default(0);
            $table->decimal('budget_cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('enquiries', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('assignee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 16)->default('todo'); // todo|doing|done
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('progress')->nullable();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('project_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('cost_date');
            $table->string('category', 32); // Material|Labour|Subcon|Transport|Equipment|Others
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->foreignId('stock_movement_id')->nullable(); // set when auto-created from stock issue
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32);
            $table->foreignId('item_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('type', 16); // in|out|project|adjust|transfer_in|transfer_out
            $table->decimal('qty', 14, 2); // signed: + adds stock, - removes stock
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('balance_after', 14, 2)->default(0);
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 64)->nullable();
            $table->string('remarks')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('moved_at');
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('status', 16)->default('draft'); // draft|ordered|partial|received|cancelled
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->decimal('qty', 14, 2);
            $table->decimal('received_qty', 14, 2)->default(0);
            $table->decimal('unit_price', 14, 4);
        });

        // ---------------- Sales documents ----------------
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('salesman_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('status', 16)->default('draft'); // draft|sent|accepted|rejected|expired
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 14, 2);
            $table->string('uom', 16)->default('LOT');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->string('type', 16)->default('invoice'); // proforma|invoice
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('draft'); // draft|unpaid|partial|paid|void
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 14, 2);
            $table->string('uom', 16)->default('LOT');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->unsignedInteger('sort')->default(0);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->string('method', 32); // Bank Transfer|Cash|Cheque|DuitNow|Card
            $table->string('reference', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // ---------------- Warranty / After-sales ----------------
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained();
            $table->string('service', 64);
            $table->text('coverage')->nullable();
            $table->date('start_date');
            $table->unsignedTinyInteger('years');
            $table->date('end_date');
            $table->string('status', 16)->default('active'); // active|expired|void
            $table->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warranty_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->default('inspection'); // inspection|repair|aftercare|complaint
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 8)->default('normal'); // low|normal|high|urgent
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->foreignId('assigned_to')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 16)->default('open'); // open|scheduled|in_progress|done|cancelled
            $table->text('resolution')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('doc_no', 32)->unique();
            $table->dateTime('occurred_at');
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32); // injury|near_miss|property_damage|vehicle|other
            $table->string('severity', 16)->default('low'); // low|medium|high|critical
            $table->string('location')->nullable();
            $table->text('description');
            $table->text('action_taken')->nullable();
            $table->string('status', 16)->default('open'); // open|investigating|closed
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'incidents', 'service_requests', 'warranties', 'payments', 'invoice_lines', 'invoices',
            'quotation_lines', 'quotations', 'purchase_order_lines', 'purchase_orders', 'stock_movements',
            'project_costs', 'project_updates', 'project_tasks',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('enquiries', fn (Blueprint $t) => $t->dropConstrainedForeignId('project_id'));
        foreach ([
            'projects', 'worker_rates', 'supplier_prices', 'stock_balances', 'items', 'suppliers', 'warehouses',
            'enquiries', 'customers', 'payslips', 'payroll_runs', 'leave_requests', 'leave_types', 'attendances',
            'announcements', 'holidays', 'settings', 'login_activities',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['user_type', 'is_active', 'last_login_at', 'deleted_at']);
        });
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('branches');
    }
};
