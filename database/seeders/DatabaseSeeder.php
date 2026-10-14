<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Enquiry;
use App\Models\Holiday;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectTask;
use App\Models\ProjectUpdate;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\Supplier;
use App\Models\SupplierPrice;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Warranty;
use App\Models\WorkerRate;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Demo data for a roofing & waterproofing contractor. All people, customers and
 * suppliers are FICTIONAL — only the company/branch names and the public
 * holiday calendar are real.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(20261013);
        $today = Carbon::today();

        // ---------------- Permissions & roles ----------------
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (array_keys(config('erp.permissions')) as $p) {
            Permission::findOrCreate($p, 'web');
        }
        foreach (config('erp.roles') as $role => $perms) {
            Role::findOrCreate($role, 'web')->syncPermissions($perms);
        }

        // ---------------- Organisation ----------------
        $kv = Branch::create(['code' => 'HQ', 'name' => 'Klang Valley', 'legal_name' => 'DEMO COMPANY SDN BHD', 'reg_no' => '000000-X', 'phone' => '+60 3-0000 0000']);
        $jb = Branch::create(['code' => 'JHR', 'name' => 'Johor', 'legal_name' => 'DEMO COMPANY (JOHOR) SDN BHD', 'phone' => '+60 7-000 0000']);
        $nr = Branch::create(['code' => 'NTH', 'name' => 'North (Ipoh / Penang / Kedah)', 'legal_name' => 'DEMO COMPANY (NORTH) SDN BHD', 'phone' => '+60 5-000 0000']);
        $branches = [$kv, $jb, $nr];

        $depts = [];
        foreach (['MGT' => 'Management', 'SAL' => 'Sales', 'OPS' => 'Operations / Site', 'STO' => 'Store', 'HRA' => 'HR & Admin', 'ACC' => 'Accounts'] as $code => $name) {
            $depts[$code] = Department::create(['code' => $code, 'name' => $name]);
        }

        foreach ([
            ['AL', 'Annual Leave', 14, true, '#1570ef'], ['MC', 'Medical Leave', 14, true, '#0f9f5a'],
            ['HL', 'Hospitalisation Leave', 60, true, '#7c3aed'], ['EL', 'Emergency Leave', 3, true, '#c97a06'],
            ['ML', 'Maternity Leave', 98, true, '#db2777'], ['PL', 'Paternity Leave', 7, true, '#0891b2'],
            ['UL', 'Unpaid Leave', 0, false, '#6b7a92'],
        ] as [$code, $name, $days, $paid, $color]) {
            LeaveType::create(['code' => $code, 'name' => $name, 'days_per_year' => $days, 'is_paid' => $paid, 'color' => $color]);
        }

        // Malaysia 2026 public holidays (as published on the company admin system)
        foreach ([
            ['2026-01-01', 'New Year\'s Day'], ['2026-02-16', 'Additional Chinese New Year Holiday'], ['2026-02-17', 'Chinese New Year'],
            ['2026-02-18', 'Chinese New Year Holiday'], ['2026-02-19', 'Additional Chinese New Year Holiday'], ['2026-03-21', 'Hari Raya Aidilfitri'],
            ['2026-03-22', 'Hari Raya Aidilfitri Holiday'], ['2026-03-23', 'Hari Raya Aidilfitri Holiday'], ['2026-05-01', 'Labour Day'],
            ['2026-05-27', 'Hari Raya Haji'], ['2026-06-01', 'Agong\'s Birthday'], ['2026-08-31', 'Merdeka Day'], ['2026-09-16', 'Malaysia Day'],
            ['2026-11-08', 'Deepavali Holiday'], ['2026-11-09', 'Deepavali Holiday'], ['2026-12-11', 'Sultan of Selangor\'s Birthday'], ['2026-12-25', 'Christmas Day'],
        ] as [$date, $name]) {
            Holiday::create(['date' => $date, 'name' => $name, 'branch_id' => str_contains($name, 'Selangor') ? $kv->id : null]);
        }

        // ---------------- Employees (fictional) ----------------
        $local = [
            ['Tan Wei Ming', 'Wei Ming', 'M', 'MGT', 'General Manager'], ['Lim Siew Ling', 'Siew Ling', 'F', 'HRA', 'HR Executive'],
            ['Ong Kah Hoe', 'KH', 'M', 'SAL', 'Senior Sales Executive'], ['Nurul Aina binti Hassan', 'Aina', 'F', 'SAL', 'Sales Executive'],
            ['Chong Jia Hui', 'Jia Hui', 'F', 'ACC', 'Account Executive'], ['Muhammad Hafiz bin Razak', 'Hafiz', 'M', 'OPS', 'Site Supervisor'],
            ['Wong Kok Leong', 'KL', 'M', 'STO', 'Storekeeper'], ['Teh Mei Yee', 'Mei Yee', 'F', 'SAL', 'Sales Coordinator'],
            ['Rajesh a/l Subramaniam', 'Rajesh', 'M', 'OPS', 'Site Supervisor'], ['Goh Chee Keong', 'CK', 'M', 'SAL', 'Sales Executive'],
            ['Siti Zulaikha binti Omar', 'Zulaikha', 'F', 'HRA', 'Admin Assistant'], ['Lee Jun Hao', 'Jun Hao', 'M', 'OPS', 'Project Coordinator'],
        ];
        $foreign = [
            ['Md Rafiqul Islam', 'Rafiq', 'BANGLADESH'], ['Abdul Karim', 'Karim', 'BANGLADESH'], ['Shahidul Hasan', 'Shahid', 'BANGLADESH'],
            ['Aung Ko Ko', 'Aung', 'MYANMAR'], ['Kyaw Zin Htet', 'Kyaw', 'MYANMAR'], ['Md Jahangir Alam', 'Jahangir', 'BANGLADESH'],
            ['Sanjay Thapa', 'Sanjay', 'NEPAL'], ['Rubel Hossain', 'Rubel', 'BANGLADESH'], ['Min Thu', 'Min', 'MYANMAR'], ['Imran Ahmed', 'Imran', 'BANGLADESH'],
        ];
        $emps = [];
        $n = 1;
        foreach ($local as $i => [$name, $nick, $g, $dept, $pos]) {
            $dob = Carbon::create(mt_rand(1975, 2001), mt_rand(1, 12), mt_rand(1, 28));
            if ($i % 4 === 0) {
                $dob->setMonth($today->month); // a few birthdays this month for the dashboard
            }
            $emps[] = Employee::create([
                'emp_no' => sprintf('E%04d', $n++), 'name' => strtoupper($name), 'nickname' => strtoupper($nick), 'gender' => $g,
                'branch_id' => $branches[$i % 3 === 2 ? 1 : 0]->id, 'department_id' => $depts[$dept]->id, 'position' => $pos,
                'employment_type' => 'permanent', 'dob' => $dob, 'nationality' => 'MALAYSIA',
                'ic_no' => $dob->format('ymd').'-'.mt_rand(10, 14).'-'.mt_rand(1000, 9999),
                'phone' => '01'.mt_rand(0, 9).'-'.mt_rand(100, 999).' '.mt_rand(1000, 9999), 'email' => strtolower(str_replace(' ', '', $nick)).'@erp.local',
                'hire_date' => $today->copy()->subDays(mt_rand(200, 2600)), 'basic_salary' => [6500, 3200, 4200, 2800, 3300, 3600, 2600, 2700, 3500, 3000, 2400, 3100][$i],
                'fixed_allowance' => [800, 200, 300, 200, 200, 400, 150, 150, 400, 300, 100, 200][$i],
                'epf_no' => (string) mt_rand(10000000, 19999999), 'socso_no' => $dob->format('ymd').mt_rand(100000, 999999),
                'cidb_no' => $dept === 'OPS' ? 'CIDB-'.mt_rand(100000, 999999) : null,
                'cidb_expiry' => $dept === 'OPS' ? $today->copy()->addDays(mt_rand(10, 300)) : null,
                'gdl_no' => in_array($dept, ['OPS', 'STO']) ? 'GDL'.mt_rand(10000, 99999) : null,
                'gdl_expiry' => in_array($dept, ['OPS', 'STO']) ? $today->copy()->addDays(mt_rand(5, 200)) : null,
                'bank_name' => ['Maybank', 'Public Bank', 'CIMB', 'Hong Leong Bank'][$i % 4], 'bank_account' => (string) mt_rand(1000000000, 1999999999),
            ]);
        }
        foreach ($foreign as $i => [$name, $nick, $nat]) {
            $dob = Carbon::create(mt_rand(1985, 2002), mt_rand(1, 12), mt_rand(1, 28));
            $emps[] = Employee::create([
                'emp_no' => sprintf('E%04d', $n++), 'name' => strtoupper($name), 'nickname' => strtoupper($nick), 'gender' => 'M',
                'branch_id' => $branches[$i % 3]->id, 'department_id' => $depts['OPS']->id, 'position' => $i % 3 === 0 ? 'Roofer' : ($i % 3 === 1 ? 'Waterproofer' : 'General Worker'),
                'employment_type' => 'foreign_worker', 'dob' => $dob, 'nationality' => $nat,
                'passport_no' => strtoupper(substr($nat, 0, 1)).chr(65 + $i).mt_rand(1000000, 9999999),
                'passport_expiry' => $today->copy()->addDays($i < 4 ? mt_rand(-3, 25) : mt_rand(120, 900)),
                'work_permit_no' => 'KSM/PLKS/'.mt_rand(100000, 999999), 'work_permit_expiry' => $today->copy()->addDays($i % 2 ? mt_rand(3, 28) : mt_rand(60, 300)),
                'cidb_no' => 'CIDB-'.mt_rand(100000, 999999), 'cidb_expiry' => $today->copy()->addDays(mt_rand(15, 400)),
                'phone' => '01'.mt_rand(0, 9).'-'.mt_rand(100, 999).' '.mt_rand(1000, 9999),
                'hire_date' => $today->copy()->subDays(mt_rand(100, 1500)), 'basic_salary' => 1700 + 100 * ($i % 4), 'fixed_allowance' => 0,
                'socso_no' => 'F'.mt_rand(10000000, 99999999),
            ]);
        }

        // ---------------- Users ----------------
        $mk = fn ($email, $name, $type, $role, $emp, $branch) => tap(User::create([
            'email' => $email, 'name' => $name, 'user_type' => $type, 'password' => 'password',
            'employee_id' => $emp?->id, 'branch_id' => $branch->id, 'is_active' => true,
        ]), fn ($u) => $role ? $u->assignRole($role) : null);
        $admin = $mk('admin@erp.local', 'System Admin', 'admin', null, null, $kv);
        $manager = $mk('manager@erp.local', 'Tan Wei Ming', 'manager', 'Manager', $emps[0], $kv);
        $hr = $mk('hr@erp.local', 'Lim Siew Ling', 'staff', 'HR & Admin', $emps[1], $kv);
        $sales1 = $mk('sales@erp.local', 'Ong Kah Hoe', 'staff', 'Salesman', $emps[2], $kv);
        $sales2 = $mk('aina@erp.local', 'Nurul Aina', 'staff', 'Salesman', $emps[3], $kv);
        $mk('accounts@erp.local', 'Chong Jia Hui', 'staff', 'Accounts', $emps[4], $kv);
        $supervisor = $mk('supervisor@erp.local', 'Muhammad Hafiz', 'staff', 'Site Supervisor', $emps[5], $kv);
        $mk('store@erp.local', 'Wong Kok Leong', 'staff', 'Storekeeper', $emps[6], $kv);
        $sales3 = $mk('ck@erp.local', 'Goh Chee Keong', 'staff', 'Salesman', $emps[9], $jb);
        $mk('staff@erp.local', 'Lee Jun Hao', 'staff', 'Staff', $emps[11], $kv);
        $salesmen = [$sales1, $sales2, $sales3, $manager];

        // ---------------- Inventory masters ----------------
        $whs = [
            Warehouse::create(['code' => 'KV-HQ', 'name' => 'Klang Valley HQ Store', 'branch_id' => $kv->id]),
            Warehouse::create(['code' => 'JB-01', 'name' => 'Johor Store', 'branch_id' => $jb->id]),
            Warehouse::create(['code' => 'NR-01', 'name' => 'North Store (Ipoh)', 'branch_id' => $nr->id]),
        ];
        $suppliers = [];
        foreach ([
            ['SUP-001', 'Bluesteel Roofing Supply Sdn Bhd', 'Mr Koh', '30 days'], ['SUP-002', 'Megah Waterproofing Trading', 'Ms Yap', 'COD'],
            ['SUP-003', 'Prima Coating Industries Sdn Bhd', 'Mr Ravi', '30 days'], ['SUP-004', 'Safeguard Fire & Safety Enterprise', 'Mr Azman', 'COD'],
            ['SUP-005', 'Hardware Hub Sdn Bhd', 'Ms Teoh', '60 days'],
        ] as [$code, $name, $cp, $terms]) {
            $suppliers[] = Supplier::create(['code' => $code, 'name' => $name, 'contact_person' => $cp, 'phone' => '03-'.mt_rand(5000, 9999).' '.mt_rand(1000, 9999), 'payment_terms' => $terms]);
        }
        $itemDefs = [
            ['ZC-GR26', 'ZINCALUME SHEET GR26', 'Roofing Sheet', 'SHEET', 38.50, 55, 40, 0],
            ['ZC-ANG9', 'ZINCALUME ANGLE 9FT', 'Accessories', 'PCS', 12.80, 20, 50, 0],
            ['PMB-EA010', 'PMB EA010 EA 20FT ALUMINIUM ANGLE', 'Accessories', 'PCS', 46.00, 68, 10, 0],
            ['HR30-PU6', 'HIGH RIB30 PU ALUMINIUM FOIL GR26 0.47MM 6FT (VOLCANIC GREY)', 'Roofing Sheet', 'SHEET', 52.00, 78, 10, 0],
            ['TD-G26-9', 'G26-0.47MM THK 762MM(W) TH DECK NO FOAM 9FT (MARAKAN)', 'Roofing Sheet', 'SHEET', 44.00, 66, 10, 0],
            ['VB-TILE', 'V-BOND ROOF TILE', 'Roof Tiles', 'PCS', 4.20, 7.5, 500, 0],
            ['APP-4MM', 'APP TORCH-ON MEMBRANE 4MM (1M x 10M)', 'Membrane', 'ROLL', 118.00, 175, 15, 1],
            ['PU-COAT20', 'PU WATERPROOF COATING 20KG', 'Coating', 'PAIL', 265.00, 390, 8, 2],
            ['ACR-COAT', 'ACRYLIC ROOF COATING 18L (WHITE)', 'Coating', 'PAIL', 180.00, 260, 8, 2],
            ['PRIMER-B', 'BITUMEN PRIMER 18L', 'Coating', 'PAIL', 95.00, 140, 6, 1],
            ['SEAL-PU', 'PU SEALANT 600ML', 'Accessories', 'PCS', 18.50, 28, 40, 4],
            ['SCR-ROOF', 'ROOFING SCREW C/W WASHER (BOX 500)', 'Accessories', 'BOX', 62.00, 90, 10, 4],
            ['GUT-6', 'ALUMINIUM GUTTER 6" (20FT)', 'Gutter', 'PCS', 88.00, 135, 10, 4],
            ['FE-1KG', '1KG FIRE EXTINGUISHER 灭火器', 'Safety', 'PCS', 45.00, 0, 5, 3],
            ['FE-6KG', '6KG FIRE EXTINGUISHER 灭火器 - 中', 'Safety', 'PCS', 120.00, 0, 2, 3],
            ['SH-BELT', 'FULL BODY SAFETY HARNESS', 'Safety', 'SET', 135.00, 0, 6, 3],
            ['UMB-BLUE', 'UMBRELLA (BLUE)', 'Tools', 'PCS', 9.00, 0, 30, 4],
            ['UMB-RED', 'UMBRELLA (RED)', 'Tools', 'PCS', 9.00, 0, 30, 4],
        ];
        $items = [];
        $stock = app(StockService::class);
        foreach ($itemDefs as [$sku, $name, $cat, $uom, $cost, $price, $reorder, $sup]) {
            $item = Item::create(['sku' => $sku, 'name' => $name, 'category' => $cat, 'uom' => $uom, 'unit_cost' => $cost, 'selling_price' => $price, 'reorder_level' => $reorder]);
            $items[$sku] = $item;
            SupplierPrice::create(['supplier_id' => $suppliers[$sup]->id, 'item_id' => $item->id, 'unit_price' => $cost, 'effective_date' => $today->copy()->subMonths(3)]);
            SupplierPrice::create(['supplier_id' => $suppliers[$sup]->id, 'item_id' => $item->id, 'unit_price' => round($cost * 1.04, 2), 'effective_date' => $today->copy()->subDays(20)]);
            // Opening stock, deliberately low on a couple of items so reorder alerts show.
            $opening = in_array($sku, ['FE-6KG', 'APP-4MM', 'PU-COAT20'], true) ? $reorder * 0.5 : $reorder * mt_rand(2, 6);
            $stock->move($item->id, $whs[0]->id, max(1, round($opening)), 'in', ['unit_cost' => $cost, 'supplier_id' => $suppliers[$sup]->id, 'reference' => 'OPENING', 'remarks' => 'Opening balance', 'moved_at' => $today->copy()->subDays(45)]);
            if (mt_rand(0, 1)) {
                $stock->move($item->id, $whs[1]->id, max(1, round($reorder * mt_rand(1, 3))), 'in', ['unit_cost' => $cost, 'reference' => 'OPENING', 'remarks' => 'Opening balance', 'moved_at' => $today->copy()->subDays(45)]);
            }
        }
        foreach ([['Roofer', 'Skilled roofer', 'day', 180], ['Waterproofer', 'Membrane / coating applicator', 'day', 170], ['General Worker', 'Helper', 'day', 110],
            ['Welder', 'Steel structure welding', 'day', 220], ['Supervisor', 'Site supervision', 'day', 250], ['Torch-on membrane (labour only)', 'Subcon rate', 'sqft', 2.80],
            ['Roof coating (labour only)', 'Subcon rate', 'sqft', 1.60], ['Gutter installation', 'Subcon rate', 'job', 450]] as [$trade, $desc, $type, $rate]) {
            WorkerRate::create(['trade' => $trade, 'description' => $desc, 'rate_type' => $type, 'rate' => $rate]);
        }

        // ---------------- Customers & pipeline ----------------
        $first = ['Mr Lim', 'Mdm Chew', 'Mr Rahman', 'Ms Tan', 'Mr Kumar', 'Mdm Wong', 'Encik Azlan', 'Mr Ho', 'Ms Lee', 'Mr Yap', 'Puan Rosnah', 'Mr Chin', 'Mdm Ng', 'Mr Ganesh', 'Ms Foo', 'Mr Teo', 'Mdm Koh', 'Encik Fauzi', 'Mr Sim', 'Ms Goh'];
        $companies = ['Sri Damai Properties Sdn Bhd', 'Taman Bukit Indah Management Corp', 'Greenfield Logistics Sdn Bhd', 'Hock Seng Hardware Trading', 'SJK(C) Chung Hwa (Lembaga Pengurus)',
            'Pangsapuri Seri Mutiara JMB', 'Evergreen Food Industries Sdn Bhd', 'Kedai Runcit Maju Jaya', 'Masjid Al-Hidayah', 'Bright Star Kindergarten'];
        $areas = [
            ['Seri Kembangan', 'Selangor', '43300'], ['Puchong', 'Selangor', '47100'], ['Cheras', 'Kuala Lumpur', '56000'], ['Petaling Jaya', 'Selangor', '46000'],
            ['Kajang', 'Selangor', '43000'], ['Klang', 'Selangor', '41200'], ['Johor Bahru', 'Johor', '80100'], ['Skudai', 'Johor', '81300'],
            ['Ipoh', 'Perak', '30450'], ['Bukit Mertajam', 'Pulau Pinang', '14000'], ['Seremban', 'Negeri Sembilan', '70200'], ['Melaka', 'Melaka', '75200'],
        ];
        $sources = config('erp.customer_sources');
        $services = config('erp.services');
        $customers = [];
        for ($i = 0; $i < 46; $i++) {
            $isCo = $i % 5 === 4;
            $area = $areas[$i % count($areas)];
            $branch = in_array($area[1], ['Johor', 'Melaka']) ? $jb : (in_array($area[1], ['Perak', 'Pulau Pinang']) ? $nr : $kv);
            $customers[] = Customer::create([
                'code' => sprintf('C%05d', 1001 + $i), 'branch_id' => $branch->id, 'type' => $isCo ? 'company' : 'individual',
                'name' => $isCo ? $companies[intdiv($i, 5) % count($companies)] : $first[$i % count($first)].' '.chr(65 + $i % 26).'.',
                'contact_person' => $isCo ? $first[($i + 3) % count($first)] : null, 'phone' => '+601'.mt_rand(0, 9).mt_rand(1000000, 9999999),
                'email' => $i % 3 === 0 ? 'customer'.($i + 1).'@example.com' : null,
                'document_type' => $isCo ? 'BRN' : ($i % 2 ? 'IC' : null), 'document_id' => $isCo ? '20'.mt_rand(1000000000, 1999999999) : null,
                'address' => mt_rand(1, 88).', Jalan '.['Mawar', 'Kenanga', 'Cempaka', 'Melati', 'Anggerik', 'Bunga Raya'][$i % 6].' '.mt_rand(1, 12).', Taman '.['Sri', 'Desa', 'Bukit', 'Indah'][$i % 4].' '.$area[0],
                'postcode' => $area[2], 'city' => $area[0], 'state' => $area[1], 'source' => $sources[$i % count($sources)],
                'status' => $i === 17 ? 'inactive' : 'active', 'created_by' => $salesmen[$i % 3]->id,
                'created_at' => $today->copy()->subDays(160 - $i * 3),
            ]);
        }

        $stages = ['new', 'new', 'inspection', 'inspection', 'quotation', 'quotation', 'negotiation', 'won', 'won', 'won', 'lost'];
        $enquiries = [];
        foreach ($customers as $i => $c) {
            $count = $i % 4 === 0 ? 2 : 1;
            for ($k = 0; $k < $count; $k++) {
                $stage = $stages[($i + $k * 3) % count($stages)];
                $date = $today->copy()->subDays(mt_rand(1, 120));
                $enquiries[] = Enquiry::create([
                    'doc_no' => sprintf('ENQ-2026/%04d', count($enquiries) + 1), 'customer_id' => $c->id, 'branch_id' => $c->branch_id,
                    'enquiry_date' => $date, 'contact_person' => $c->contact_person ?? $c->name, 'contact_no' => $c->phone,
                    'service' => $services[($i + $k) % count($services)], 'site_address' => $c->address.', '.$c->postcode.' '.$c->city, 'state' => $c->state,
                    'inspection_date' => $stage === 'new' ? null : $date->copy()->addDays(mt_rand(1, 5)), 'salesman_id' => $salesmen[($i + $k) % 4]->id,
                    'stage' => $stage, 'estimate_amount' => $stage === 'new' ? null : mt_rand(18, 450) * 100,
                    'feedback' => ['Customer wants to compare 2 quotations', 'Roof leaking at kitchen area, urgent', 'Will decide after CNY', 'Asked for V-Bond sample', 'Prefers weekend inspection', null][($i + $k) % 6],
                    'lost_reason' => $stage === 'lost' ? ['Price too high', 'Chose other contractor', 'Project postponed'][$i % 3] : null,
                ]);
            }
        }

        // ---------------- Quotations / projects / invoices ----------------
        $terms = "1. 50% deposit upon confirmation, balance upon completion.\n2. Quotation valid for 30 days.\n3. Warranty as stated in the warranty certificate.";
        $projStatuses = ['in_progress', 'in_progress', 'in_progress', 'scheduled', 'scheduled', 'completed', 'completed', 'completed', 'on_hold', 'planning'];
        $q = 0;
        $p = 0;
        $inv = 0;
        foreach ($enquiries as $e) {
            if (! in_array($e->stage, ['quotation', 'negotiation', 'won', 'lost'], true)) {
                continue;
            }
            $lines = $this->linesFor($e->service, $items);
            $sub = array_sum(array_column($lines, 'amount'));
            $qDate = $e->inspection_date->copy()->addDays(2);
            $quote = Quotation::create([
                'doc_no' => sprintf('QT-2026/%04d', ++$q), 'customer_id' => $e->customer_id, 'enquiry_id' => $e->id, 'salesman_id' => $e->salesman_id,
                'quote_date' => $qDate, 'valid_until' => $qDate->copy()->addDays(30),
                'status' => ['quotation' => 'sent', 'negotiation' => 'sent', 'won' => 'accepted', 'lost' => 'rejected'][$e->stage],
                'subtotal' => $sub, 'discount' => 0, 'tax_rate' => 0, 'tax' => 0, 'total' => $sub, 'terms' => $terms,
            ]);
            foreach ($lines as $s => $l) {
                $quote->lines()->create($l + ['sort' => $s]);
            }
            $e->update(['estimate_amount' => $sub]);
            if ($e->stage !== 'won') {
                continue;
            }

            $status = $projStatuses[$p % count($projStatuses)];
            $start = match ($status) {
                'completed' => $today->copy()->subDays(mt_rand(25, 90)), 'scheduled', 'planning' => $today->copy()->addDays(mt_rand(2, 25)),
                default => $today->copy()->subDays(mt_rand(1, 12)),
            };
            $days = mt_rand(3, 14);
            $customer = $e->customer;
            $project = Project::create([
                'code' => sprintf('PR-2026/%04d', 2001 + $p++), 'name' => $e->service.' — '.$customer->city, 'customer_id' => $customer->id,
                'branch_id' => $customer->branch_id, 'enquiry_id' => $e->id, 'category' => $p % 9 === 0 ? 'aftercare' : 'normal', 'service' => $e->service,
                'site_address' => $e->site_address, 'state' => $e->state, 'manager_id' => [$supervisor, $manager][$p % 2]->id,
                'start_date' => $start, 'end_date' => $start->copy()->addDays($days), 'status' => $status,
                'progress' => match ($status) {
                    'completed' => 100, 'in_progress' => mt_rand(15, 85), 'on_hold' => 40, default => 0
                },
                'contract_value' => $sub, 'budget_cost' => round($sub * 0.62, -1),
            ]);
            $e->update(['project_id' => $project->id]);
            $quote->update(['project_id' => $project->id]);

            foreach (['Site measurement & safety setup', 'Remove existing roofing / surface prep', 'Install / apply main works', 'Water test & touch-up', 'Handover & cleaning'] as $s => $title) {
                $tStart = $start->copy()->addDays((int) floor($s * $days / 5));
                ProjectTask::create([
                    'project_id' => $project->id, 'title' => $title, 'assignee_id' => $emps[12 + ($p + $s) % 10]->id,
                    'start_date' => $tStart, 'end_date' => $tStart->copy()->addDays(max(1, (int) floor($days / 5))), 'sort' => $s,
                    'status' => $project->progress >= ($s + 1) * 20 ? 'done' : ($project->progress >= $s * 20 && $project->progress > 0 ? 'doing' : 'todo'),
                ]);
            }
            if ($status !== 'planning' && $status !== 'scheduled') {
                ProjectUpdate::create(['project_id' => $project->id, 'user_id' => $supervisor->id, 'progress' => min(100, $project->progress), 'note' => 'Site work ongoing, weather OK. Materials delivered.', 'created_at' => $start->copy()->addDays(1)]);
                foreach (array_slice($lines, 0, 2) as $l) {
                    if ($l['item_id']) {
                        $stock->move($l['item_id'], $whs[0]->id, -min((float) $l['qty'], 2), 'project', ['project_id' => $project->id, 'reference' => $project->code, 'moved_at' => $start->copy()->addDay()]);
                    }
                }
                $crew = mt_rand(2, 4);
                ProjectCost::create(['project_id' => $project->id, 'cost_date' => $start, 'category' => 'Labour', 'description' => "$crew workers × ".min($days, 6).' days', 'amount' => round($sub * mt_rand(14, 24) / 100, -1)]);
                ProjectCost::create(['project_id' => $project->id, 'cost_date' => $start, 'category' => 'Transport', 'description' => 'Lorry delivery to site', 'amount' => min(450, round($sub * 0.03, -1))]);
                if ($p % 3 === 0) {
                    ProjectCost::create(['project_id' => $project->id, 'cost_date' => $start->copy()->addDay(), 'category' => 'Equipment', 'description' => 'Scaffolding rental', 'amount' => mt_rand(30, 90) * 10, 'supplier_id' => $suppliers[4]->id]);
                }
            }

            // Deposit invoice for every confirmed job, final invoice for completed ones.
            $mkInv = function ($pct, $date, $label) use (&$inv, $customer, $project, $quote) {
                $amt = round($quote->total * $pct, 2);
                $invoice = Invoice::create([
                    'doc_no' => sprintf('INV-2026/%04d', ++$inv), 'type' => 'invoice', 'customer_id' => $customer->id,
                    'project_id' => $project->id, 'quotation_id' => $quote->id, 'invoice_date' => $date, 'due_date' => $date->copy()->addDays(30),
                    'status' => 'unpaid', 'subtotal' => $amt, 'total' => $amt,
                ]);
                $invoice->lines()->create(['description' => "$label — {$project->code} {$project->name}", 'qty' => 1, 'uom' => 'LOT', 'unit_price' => $amt, 'amount' => $amt]);

                return $invoice;
            };
            $deposit = $mkInv(0.5, $start->copy()->subDays(5), '50% deposit');
            if ($p % 4 !== 1) {
                Payment::create(['invoice_id' => $deposit->id, 'paid_on' => $start->copy()->subDays(3), 'amount' => $deposit->total, 'method' => 'Bank Transfer', 'reference' => 'IBG'.mt_rand(100000, 999999)]);
                $deposit->update(['paid_amount' => $deposit->total, 'status' => 'paid']);
            }
            if ($status === 'completed') {
                $final = $mkInv(0.5, $project->end_date->copy()->addDay(), 'Balance 50% upon completion');
                if ($p % 3 === 0) {
                    $part = round($final->total * 0.4, 2);
                    Payment::create(['invoice_id' => $final->id, 'paid_on' => $project->end_date->copy()->addDays(10), 'amount' => $part, 'method' => 'DuitNow', 'reference' => 'DN'.mt_rand(100000, 999999)]);
                    $final->update(['paid_amount' => $part, 'status' => 'partial']);
                }
                $years = [1, 3, 5][$p % 3];
                Warranty::create([
                    'doc_no' => sprintf('WR-2026/%04d', $p), 'project_id' => $project->id, 'customer_id' => $customer->id, 'service' => $project->service,
                    'coverage' => 'Workmanship and water-tightness of the treated roof area. Excludes damage from third-party works, structural movement or natural disasters.',
                    'start_date' => $project->end_date, 'years' => $years, 'end_date' => $project->end_date->copy()->addYears($years),
                ]);
            }
        }
        // An older warranty about to expire, for the warranty list.
        Warranty::create(['doc_no' => 'WR-2025/0007', 'customer_id' => $customers[5]->id, 'service' => 'Roof Coating', 'coverage' => 'Roof coating workmanship.', 'start_date' => $today->copy()->subYear()->addDays(12), 'years' => 1, 'end_date' => $today->copy()->addDays(12)]);

        // ---------------- After-sales ----------------
        $projects = Project::all();
        $workers = array_slice($emps, 12);
        foreach ([
            ['inspection', 'Site inspection — leaking at porch roof', 'high', 0, 10], ['repair', 'Re-seal skylight joint', 'normal', 1, 14],
            ['aftercare', 'Aftercare visit — 6 month check', 'low', 2, 9], ['complaint', 'Water stain on ceiling after heavy rain', 'urgent', 0, 15],
            ['inspection', 'Inspect metal roof rust spots', 'normal', 3, 11], ['repair', 'Replace 3 cracked V-Bond tiles', 'normal', 4, 16],
            ['inspection', 'Flat roof ponding check', 'normal', -2, 10], ['repair', 'Gutter overflow fix', 'high', -5, 9],
        ] as $i => [$type, $title, $prio, $dayOffset, $hour]) {
            $proj = $projects[$i % $projects->count()];
            $when = $today->copy()->startOfWeek()->addDays($dayOffset + 1)->setTime($hour, 0);
            ServiceRequest::create([
                'doc_no' => sprintf('SR-2026/%04d', $i + 1), 'customer_id' => $proj->customer_id, 'project_id' => $type === 'inspection' ? null : $proj->id,
                'warranty_id' => $type === 'aftercare' ? Warranty::first()?->id : null, 'type' => $type, 'title' => $title, 'priority' => $prio,
                'description' => 'Customer called in via WhatsApp. Photos attached in chat.', 'scheduled_at' => $when, 'duration_minutes' => [60, 90, 120][$i % 3],
                'assigned_to' => $workers[$i % count($workers)]->id, 'status' => $when->isPast() ? ($i % 3 ? 'done' : 'in_progress') : 'scheduled',
                'resolution' => $when->isPast() && $i % 3 ? 'Resealed and water-tested, no further leak.' : null, 'created_by' => $sales1->id,
            ]);
        }
        Incident::create(['doc_no' => 'INC-2026/0001', 'occurred_at' => $today->copy()->subDays(9)->setTime(14, 20), 'project_id' => $projects[0]->id, 'employee_id' => $workers[1]->id,
            'type' => 'near_miss', 'severity' => 'medium', 'location' => 'Roof edge, east side', 'description' => 'Worker slipped near roof edge; harness lanyard held. No injury.',
            'action_taken' => 'Toolbox talk held; additional anchor point installed.', 'status' => 'closed', 'reported_by' => $supervisor->id]);
        Incident::create(['doc_no' => 'INC-2026/0002', 'occurred_at' => $today->copy()->subDays(2)->setTime(10, 5), 'project_id' => $projects[1 % $projects->count()]->id,
            'type' => 'property_damage', 'severity' => 'low', 'location' => 'Customer porch', 'description' => 'Ceiling board at porch dented while carrying sheets.',
            'action_taken' => 'Replacement arranged at company cost.', 'status' => 'investigating', 'reported_by' => $supervisor->id]);

        // ---------------- Purchase orders ----------------
        $po = PurchaseOrder::create(['doc_no' => 'PO-2026/0001', 'supplier_id' => $suppliers[1]->id, 'warehouse_id' => $whs[0]->id, 'order_date' => $today->copy()->subDays(3), 'expected_date' => $today->copy()->addDays(4), 'status' => 'ordered', 'created_by' => $admin->id]);
        $po->lines()->create(['item_id' => $items['APP-4MM']->id, 'qty' => 30, 'unit_price' => 118]);
        $po->lines()->create(['item_id' => $items['PRIMER-B']->id, 'qty' => 10, 'unit_price' => 95]);
        $po->update(['total' => 30 * 118 + 10 * 95]);
        $po2 = PurchaseOrder::create(['doc_no' => 'PO-2026/0002', 'supplier_id' => $suppliers[3]->id, 'warehouse_id' => $whs[0]->id, 'order_date' => $today, 'status' => 'draft', 'created_by' => $admin->id]);
        $po2->lines()->create(['item_id' => $items['FE-6KG']->id, 'qty' => 4, 'unit_price' => 120]);
        $po2->update(['total' => 480]);

        // ---------------- HR transactions ----------------
        Announcement::create(['title' => 'Memo — Client follow-up obligations & project team takeover policy', 'body' => 'Sales must update every enquiry stage within 48 hours of inspection. Projects handed over to the site team require a signed checklist.', 'published_on' => $today->copy()->subDays(24), 'is_pinned' => true, 'created_by' => $hr->id]);
        Announcement::create(['title' => 'Memo — Mandatory site leader attendance & daily WhatsApp reporting', 'body' => 'Site leaders must clock in at site and post a progress photo before 10am daily.', 'published_on' => $today->copy()->subDays(60), 'created_by' => $hr->id]);
        Announcement::create(['title' => 'Memo — Timely submission of quotations, proforma invoices and work estimates', 'body' => 'Quotations must be issued within 3 working days of inspection.', 'published_on' => $today->copy()->subDays(130), 'created_by' => $hr->id]);
        Announcement::create(['title' => 'Memo — Public holidays 2026', 'body' => 'Refer to the Holidays page for the full list.', 'published_on' => Carbon::create(2026, 1, 6), 'created_by' => $hr->id]);

        $types = LeaveType::pluck('id', 'code');
        foreach ([[1, 'AL', 8, 2, 'pending', 'Family trip'], [3, 'MC', -6, 1, 'approved', 'Fever'], [8, 'AL', 15, 3, 'pending', 'Balik kampung'],
            [14, 'EL', -2, 1, 'approved', 'Family emergency'], [17, 'AL', 22, 2, 'pending', 'Personal matters'], [2, 'AL', -20, 1, 'rejected', 'Clashes with site handover']] as [$e, $t, $offset, $days, $status, $reason]) {
            $start = $today->copy()->addDays($offset);
            LeaveRequest::create(['employee_id' => $emps[$e]->id, 'leave_type_id' => $types[$t], 'start_date' => $start, 'end_date' => $start->copy()->addDays($days - 1), 'days' => $days,
                'reason' => $reason, 'status' => $status, 'reviewed_by' => $status === 'pending' ? null : $hr->id, 'reviewed_at' => $status === 'pending' ? null : $start->copy()->subDays(3)]);
        }

        // Attendance for the last 10 days (none for today — staff clock in themselves).
        for ($d = 10; $d >= 1; $d--) {
            $day = $today->copy()->subDays($d);
            if ($day->isSunday()) {
                continue;
            }
            foreach ($emps as $i => $emp) {
                if (mt_rand(1, 100) <= 6) {
                    continue; // absent
                }
                $in = $day->copy()->setTime(8, 0)->addMinutes(mt_rand(0, 100) < 85 ? mt_rand(5, 38) : mt_rand(41, 75));
                Attendance::create(['employee_id' => $emp->id, 'work_date' => $day, 'clock_in' => $in,
                    'clock_out' => $day->copy()->setTime($day->isSaturday() ? 13 : 17, 30)->addMinutes(mt_rand(0, 60)),
                    'is_late' => $in->gt($day->copy()->setTime(8, 40)), 'source' => $i >= 12 ? 'mobile' : 'web']);
            }
        }
    }

    /** Typical quotation lines per service (material + labour), priced from the item master. */
    private function linesFor(string $service, array $items): array
    {
        $area = mt_rand(4, 30) * 50; // sq ft
        $mat = match (true) {
            str_contains($service, 'Membrane') || str_contains($service, 'Flat') => [['APP-4MM', ceil($area / 100)], ['PRIMER-B', ceil($area / 400)]],
            str_contains($service, 'Coating') || str_contains($service, 'Wall') => [['PU-COAT20', ceil($area / 250)], ['ACR-COAT', ceil($area / 300)]],
            str_contains($service, 'V-Bond') => [['VB-TILE', $area * 1.1], ['SEAL-PU', 6]],
            str_contains($service, 'Gutter') => [['GUT-6', ceil($area / 120)], ['SEAL-PU', 4]],
            default => [['ZC-GR26', ceil($area / 20)], ['SCR-ROOF', ceil($area / 600)], ['SEAL-PU', 6]],
        };
        $lines = [];
        foreach ($mat as [$sku, $qty]) {
            $it = $items[$sku];
            $lines[] = ['item_id' => $it->id, 'description' => $it->name, 'qty' => $qty, 'uom' => $it->uom, 'unit_price' => $it->selling_price, 'amount' => round($qty * (float) $it->selling_price, 2)];
        }
        $rate = str_contains($service, 'Coating') ? 3.5 : 4.5;
        $lines[] = ['item_id' => null, 'description' => "Labour & workmanship — $service ($area sq ft)", 'qty' => $area, 'uom' => 'SQFT', 'unit_price' => $rate, 'amount' => round($area * $rate, 2)];
        $lines[] = ['item_id' => null, 'description' => 'Transportation, scaffolding & site cleaning', 'qty' => 1, 'uom' => 'LOT', 'unit_price' => 350, 'amount' => 350];

        return $lines;
    }
}
