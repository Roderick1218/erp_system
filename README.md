# ERP System

**English** · [中文](#中文说明)

A web-based ERP for small and medium contracting / trading companies. It covers sales, projects, inventory, after-sales, HR and costing, with an English / 中文 interface.
Built with Laravel 13 · PHP 8.3 · MySQL / MariaDB. No Node.js build step is needed.

![Login](docs/screenshots/login.png)

---

## Contents
- [Features](#features)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Demo accounts](#demo-accounts)
- [User guide](#user-guide)
- [For developers](#for-developers)
- [Important notes](#important-notes)

## Features

| Module | What you can do |
|---|---|
| **Dashboard** | Clock in / out, KPIs for your role, announcements, pending work, sales pipeline, invoiced-per-month chart, upcoming holidays, birthdays, passport / work permit / CIDB / GDL expiry alerts |
| **Customer** | Customer profiles, enquiry pipeline (list + drag-and-drop board), customer report, salesman report, CSV export |
| **Sales** | Quotations → invoices / proforma invoices, line items with automatic totals, print / save as PDF, payments received, overdue tracking |
| **Project** | List, board, month calendar and 6-week schedule views; tasks, site progress updates, costs vs budget, materials used, documents, history |
| **Inventory** | Stock master, stock card, stock in / out / issue to project / adjustment / transfer (never goes negative, moving-average cost), purchase orders with partial receiving, suppliers & price history, warehouses, worker rates |
| **After-sales** | Warranties with printable certificate, service requests with appointment calendar, incident reports |
| **HR** | Employees and their documents, attendance board & monthly report, leave apply / approve (skips Sundays & public holidays), payroll runs & payslips |
| **Costing** | Project cost management (budget vs actual by category), costing summary report |
| **System** | Users, roles & permissions, company settings, audit log, login activity |
| **Everyone** | My profile (own contact details, login email, password), English / 中文 switch, dark mode, global search (Ctrl + K) |

## Screenshots

| | |
|---|---|
| ![Dashboard](docs/screenshots/dashboard.png) **Dashboard** | ![Enquiry board](docs/screenshots/enquiries-board.png) **Enquiry pipeline (board)** |
| ![Quotation](docs/screenshots/quotation.png) **Quotation** | ![Quotation print](docs/screenshots/quotation-print.png) **Quotation print / PDF** |
| ![Project board](docs/screenshots/projects-board.png) **Project board** | ![Project detail](docs/screenshots/project-detail.png) **Project detail** |
| ![Project schedule](docs/screenshots/project-schedule.png) **Project schedule** | ![Stock](docs/screenshots/stock.png) **Stock transactions** |
| ![Purchase order](docs/screenshots/purchase-orders.png) **Purchase order** | ![Service calendar](docs/screenshots/service-calendar.png) **Service appointments** |
| ![Attendance](docs/screenshots/attendance-board.png) **Attendance board** | ![Leave approvals](docs/screenshots/leave-approvals.png) **Leave approvals** |
| ![Employees](docs/screenshots/employees.png) **Employees** | ![Costing](docs/screenshots/costing.png) **Costing summary** |
| ![Roles](docs/screenshots/roles.png) **Roles & permissions** | ![Profile](docs/screenshots/profile.png) **My profile** |
| ![Dashboard in Chinese](docs/screenshots/dashboard-zh.png) **中文 interface** | ![Mobile](docs/screenshots/dashboard-mobile.png) **Mobile** |

## Installation

### Requirements
- PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `intl` (optional)
- Composer 2
- MySQL 8 or MariaDB 10.6+
- Apache (XAMPP works) or any web server pointing to `public/`

### Steps (Windows + XAMPP)
```bash
cd C:\xampp\htdocs
git clone https://github.com/Roderick1218/erp_system.git erp
cd erp
composer install
copy .env.example .env          # Linux / macOS: cp .env.example .env
php artisan key:generate
```
1. In phpMyAdmin, create an empty database called `erp` (utf8mb4).
2. Open `.env` and check `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL`.
3. Create the tables and demo data:
   ```bash
   php artisan migrate --seed
   ```
4. Open **http://localhost/erp** (the root `.htaccess` sends requests to `public/`).
   Or run `php artisan serve` and open http://127.0.0.1:8000.

> To start over with fresh demo data: `php artisan migrate:fresh --seed`. **This wipes the whole database.** Only use it while testing.

### Going live
- Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
- Point the web server's document root to `public/`.
- Run `php artisan migrate --force` (no `--seed`), then sign in as the admin and **change the admin password** and **remove or deactivate the demo users**.
- Fill in your company name and registration number under **System → Settings**. They appear on quotations and invoices.

## Demo accounts

All demo accounts use the password **`password`**. On a local install, the login page has buttons that fill these in for you.

| Email | Role | Can see |
|---|---|---|
| admin@erp.local | Admin | Everything |
| manager@erp.local | Manager | Everything except users / roles / payroll |
| sales@erp.local | Salesman | Customers, enquiries, quotations, invoices |
| supervisor@erp.local | Site Supervisor | Projects, after-sales, stock (view) |
| store@erp.local | Storekeeper | Inventory, purchasing |
| hr@erp.local | HR & Admin | Employees, attendance, leave, payroll |
| accounts@erp.local | Accounts | Invoices, payments, costing |
| staff@erp.local | Staff | Own attendance and leave only |

The demo data (customers, staff, projects, amounts) is fictional.

## User guide

### 1. Sign in and language
- Open the system, enter your email and password, and click **Sign in**. The eye icon shows the password.
- Use **English / 中文** at the top right of the login page, or the **中 / EN** button in the top bar after signing in. Your choice is saved to your account.
- Forgot your password? Ask HR or the admin to reset it, then change it under **My profile**.

### 2. Getting around
- **Sidebar**: modules on the left. Click « Collapse » to make it narrow. On a phone, open it with ☰.
- **Search (Ctrl + K)**: find customers, enquiries, projects, quotations, invoices, stock items or employees, or jump to any page.
- **+ New**: shortcut to create an enquiry, quotation, project, purchase order and more.
- **🔔 Bell**: things waiting for you: leave to approve, overdue invoices, low stock, staff documents about to expire, service jobs assigned to you.
- **🌙**: switch between light and dark mode.
- Many lists can switch between **List / Board / Calendar** views and have **Export CSV**.

### 3. Dashboard and clock-in
- Click **Clock In** when you arrive and **Clock Out** when you leave. Nothing is recorded until you press the button. Arriving after the work start time (plus grace minutes) is marked **Late**.
- The dashboard shows the KPIs, pending work, announcements and expiry alerts that your role is allowed to see.

### 4. Sales flow: enquiry → quotation → invoice → payment
1. **Customer → Enquiries → New enquiry**: pick or create the customer, service, site address and salesman.
2. Move the enquiry through the stages (New → Inspection → Quotation → Negotiation → Won / Lost) by dragging the card on the **Board**, or by editing it. A lost enquiry needs a reason.
3. On the enquiry, click **Create quotation**. Add line items; subtotal, discount, tax and total are calculated for you.
4. On the quotation: **Print / PDF** to send to the customer → **Mark as sent** → **Customer accepted** → **Create invoice** (an invoice or proforma invoice).
5. On the invoice, click **Record payment** whenever money comes in. The invoice becomes *Partially paid* or *Paid* by itself. Overdue invoices show on the dashboard and in the bell.
6. **Customer → Customer report / Salesman report** show the conversion and value per customer or salesman.

### 5. Projects
1. From a won enquiry click **Convert to project** (or **Project → New project**). Set the contract value, budget cost, dates and manager.
2. On the project page:
   - **Overview**: post site progress updates with a progress %.
   - **Tasks**: checklist for the job.
   - **Costs**: material / labour / subcon / transport / equipment / other costs against the budget.
   - **Stock used**: materials issued from the store (added automatically by inventory).
   - **Documents**: upload drawings, photos and contracts.
   - **History**: who changed what.
3. **Invoice** creates an invoice for the project. When the job is done, **Issue warranty** or open a **Service request**.
4. **Project Board / Calendar / Schedule** give the planning views across all projects.

### 6. Inventory and purchasing
- **Stock Master**: items with SKU, unit, reorder level and current stock per warehouse. Click an item to see its stock card (every movement and running balance).
- **Stock Transactions → Record movement**: Stock in, Stock out, Adjustment. Use **Issue to project** to charge material to a project's cost, and **Transfer** to move stock between warehouses. Stock can never go below zero.
- **Purchase Orders**: create a draft → **Mark as ordered** → **Receive goods** when delivery arrives (part deliveries are fine). Received goods go into stock automatically and update the supplier's price history.
- **Suppliers**, **Warehouses** and **Worker Rates** keep the master data.

### 7. After-sales
- **Warranties**: issue a warranty from a project, print the **Certificate**, and register a **Warranty claim** when the customer calls.
- **Service requests**: book site visits; the **calendar** shows the week's appointments.
- **Incident reports**: record site incidents with severity and follow-up.

### 8. HR
- **My Attendance / My Leave**: every staff member sees their own records. Click **Apply for leave**, choose the type and dates; Sundays and public holidays are not counted.
- **Leave Approval** (HR / manager): **Approve** or **Reject** each request.
- **Employees**: personal details, salary, bank, passport / permit / CIDB / GDL numbers and expiry dates, and uploaded documents.
- **Attendance Board**: who is in, late, on leave or not clocked in today. HR can add or correct entries. **Monthly report** gives the totals per person.
- **Payroll**: **Run payroll** for a month → **Generate draft** → check and adjust overtime / commission / deductions → **Finalize** (this locks the payslips). Staff can open their own payslips.
- **Holidays** and **Announcements** are managed here and appear on everyone's dashboard.

### 9. Costing
- **Project Cost Management**: budget vs actual cost for every project, by category.
- **Costing Summary Report**: cost, invoiced revenue and gross margin by month, project or category. Can be exported to CSV.

### 10. System (admin)
- **Users**: create logins, link each one to an employee, set branch and role, deactivate leavers.
- **Roles & permissions**: tick what each role can view or manage. Admin users always have full access.
- **Settings**: company name, registration number, work start time, late grace minutes, SST rate, quotation validity, invoice due days, default quotation terms; plus tabs for **branches**, **departments** and **leave types**.
- **Audit log / Login activity**: who changed what and who signed in.

### 11. My profile
Open the avatar menu (top right) → **My profile** / **Change password**.
- You can change your display name, nickname, phone, personal email, address, emergency contact and language.
- Changing your **login email** needs your current password.
- A new password must be at least 8 characters, with letters and numbers.
- IC, bank account and salary can only be changed by HR.

## For developers

| Path | What it holds |
|---|---|
| `config/erp.php` | Permissions, default roles, sidebar / Ctrl+K menu, service / state / UOM lists |
| `app/Services/StockService.php` | The only place stock quantities change |
| `app/Services/AttendanceService.php`, `StatutoryCalculator.php` | Attendance rules, EPF / SOCSO / EIS estimate |
| `app/View/LayoutComposer.php` | Menu filtering by permission, bell notifications |
| `public/css/app.css`, `public/js/app.js` | Design system (tokens at the top) and small vanilla-JS behaviours (`data-*`) |
| `resources/views/components/*` | Blade components: page header, badge, stat, field, empty state, icon |
| `lang/json/<module>/zh_CN.json` | Chinese translations. The English text is the key, e.g. `__('Customer')` |
| `database/seeders/DatabaseSeeder.php` | Demo data |

```bash
php artisan test        # 50 feature tests, runs on in-memory SQLite (does not touch MySQL)
```

Adding a page: register the route in `routes/web.php`, add it to `menu` in `config/erp.php` with the permission it needs, and wrap any new text in `__('...')` with its Chinese in the matching `zh_CN.json`.

## Important notes
- **Payroll EPF / SOCSO / EIS are simplified estimates.** Check them against the official KWSP / PERKESO tables before paying. PCB (income tax) is not included.
- Customer-facing printouts (quotation, invoice, warranty certificate) are always in English. Payslips follow the user's language.
- Text people type (customer names, remarks, announcements) is shown exactly as entered and is not translated.

---

<a id="中文说明"></a>

# 中文说明

[English](#erp-system) · **中文**

一套网页版 ERP 系统，适合中小型工程 / 贸易公司使用。功能涵盖销售、项目、库存、售后、人事和成本，界面可以在英文和中文之间切换。
技术栈：Laravel 13 · PHP 8.3 · MySQL / MariaDB，不需要 Node.js 编译。

![登录页](docs/screenshots/login-zh.png)

## 目录
- [功能](#功能)
- [截图](#截图)
- [安装](#安装)
- [演示账号](#演示账号)
- [使用说明](#使用说明)
- [开发者说明](#开发者说明)
- [注意事项](#注意事项)

## 功能

| 模块 | 可以做什么 |
|---|---|
| **仪表板** | 上下班打卡、按角色显示的关键指标、公告、待办事项、销售管道、每月开票图表、即将到来的公假、生日、护照 / 工作准证 / CIDB / GDL 到期提醒 |
| **客户** | 客户资料、询价管道（列表 + 拖拽看板）、客户报表、销售员报表、导出 CSV |
| **销售** | 报价单 → 发票 / 形式发票，明细自动计算总额，打印 / 另存 PDF，收款记录，逾期追踪 |
| **项目** | 列表、看板、月历、6 周排程；任务、现场进度、成本对比预算、领用物料、文件、历史记录 |
| **库存** | 物料主档、库存卡、入库 / 出库 / 领料到项目 / 调整 / 调拨（库存不会变负数，移动平均成本），采购单可分批收货，供应商与价格历史，仓库，工人费率 |
| **售后** | 保修（可打印保修证书）、维修服务预约日历、事故报告 |
| **人事** | 员工档案与证件、考勤看板与月报、请假申请 / 审批（自动跳过星期日和公假）、薪资计算与工资单 |
| **成本** | 项目成本管理（各类别预算对比实际）、成本汇总报表 |
| **系统** | 用户、角色与权限、公司设置、操作日志、登录记录 |
| **所有人** | 我的资料（自己的联系资料、登录电邮、密码）、中英文切换、深色模式、全局搜索（Ctrl + K） |

## 截图

| | |
|---|---|
| ![仪表板](docs/screenshots/dashboard-zh.png) **仪表板（中文）** | ![询价看板](docs/screenshots/enquiries-board.png) **询价管道看板** |
| ![报价单](docs/screenshots/quotation.png) **报价单** | ![报价单打印](docs/screenshots/quotation-print.png) **报价单打印 / PDF** |
| ![项目看板](docs/screenshots/projects-board.png) **项目看板** | ![项目详情](docs/screenshots/project-detail.png) **项目详情** |
| ![库存](docs/screenshots/stock.png) **库存流水** | ![采购单](docs/screenshots/purchase-orders.png) **采购单** |
| ![考勤](docs/screenshots/attendance-board.png) **考勤看板** | ![请假审批](docs/screenshots/leave-approvals.png) **请假审批** |
| ![成本](docs/screenshots/costing.png) **成本汇总** | ![我的资料](docs/screenshots/profile.png) **我的资料** |

更多截图请看上面英文部分，或 `docs/screenshots/` 文件夹。

## 安装

### 需要
- PHP 8.3 以上，开启 `pdo_mysql`、`mbstring`、`openssl`、`fileinfo`
- Composer 2
- MySQL 8 或 MariaDB 10.6 以上
- Apache（XAMPP 可以），或任何指向 `public/` 的网页服务器

### 步骤（Windows + XAMPP）
```bash
cd C:\xampp\htdocs
git clone https://github.com/Roderick1218/erp_system.git erp
cd erp
composer install
copy .env.example .env          # Linux / macOS 用：cp .env.example .env
php artisan key:generate
```
1. 在 phpMyAdmin 新建一个空数据库，名称 `erp`（utf8mb4）。
2. 打开 `.env`，检查 `DB_HOST`、`DB_PORT`、`DB_DATABASE`、`DB_USERNAME`、`DB_PASSWORD`、`APP_URL`。
   XAMPP 的 MySQL 如果不是 3306 端口，要改 `DB_PORT`。
3. 建立数据表和演示数据：
   ```bash
   php artisan migrate --seed
   ```
4. 打开 **http://localhost/erp**（根目录的 `.htaccess` 会自动转到 `public/`）。
   也可以用 `php artisan serve`，然后打开 http://127.0.0.1:8000。

> 想重置成全新的演示数据：`php artisan migrate:fresh --seed`。**这会清空整个数据库**，只在测试时用。

### 正式上线
- `.env` 里设 `APP_ENV=production`、`APP_DEBUG=false`。
- 网页服务器的根目录指向 `public/`。
- 执行 `php artisan migrate --force`（不要加 `--seed`），然后用管理员登录，**修改管理员密码**，并**删除或停用演示账号**。
- 在 **系统 → 设置** 填入公司名称和注册号，会显示在报价单和发票上。

## 演示账号

所有演示账号的密码都是 **`password`**。本机运行时，登录页有按钮可以一键填入。

| 电邮 | 角色 | 能看到 |
|---|---|---|
| admin@erp.local | 管理员 | 全部 |
| manager@erp.local | 经理 | 除用户 / 角色 / 薪资外全部 |
| sales@erp.local | 销售员 | 客户、询价、报价、发票 |
| supervisor@erp.local | 工地主管 | 项目、售后、库存（只看） |
| store@erp.local | 仓管 | 库存、采购 |
| hr@erp.local | 人事行政 | 员工、考勤、请假、薪资 |
| accounts@erp.local | 会计 | 发票、收款、成本 |
| staff@erp.local | 员工 | 只有自己的打卡和请假 |

演示数据（客户、员工、项目、金额）都是虚构的。

## 使用说明

### 1. 登录和语言
- 输入电邮和密码，按 **登录**。点眼睛图标可以显示密码。
- 登录页右上角可以选 **English / 中文**；登录后用顶部栏的 **中 / EN** 按钮切换。选好的语言会记在你的账号里。
- 忘记密码？请人事或管理员帮你重置，然后到 **我的资料** 改成自己的密码。

### 2. 基本操作
- **左边菜单**：所有模块。按「收起」可以变窄；手机上按 ☰ 打开。
- **搜索（Ctrl + K）**：找客户、询价、项目、报价单、发票、物料、员工，或直接跳到任何页面。
- **+ 新建**：快速新建询价、报价单、项目、采购单等。
- **🔔 铃铛**：等你处理的事情：待审批请假、逾期发票、低库存、快到期的员工证件、分配给你的服务单。
- **🌙**：切换浅色 / 深色模式。
- 很多列表可以切换 **列表 / 看板 / 日历**，也可以 **导出 CSV**。

### 3. 仪表板和打卡
- 到公司按 **上班打卡**，下班按 **下班打卡**。不按按钮就不会记录。超过上班时间（加宽限分钟）会标记为 **迟到**。
- 仪表板只显示你的角色有权限看的指标、待办事项、公告和到期提醒。

### 4. 销售流程：询价 → 报价 → 发票 → 收款
1. **客户 → 询价 → 新询价**：选择或新建客户，填服务类型、工地地址、负责销售员。
2. 在 **看板** 上拖动卡片（或编辑询价）来推进阶段：新建 → 勘察 → 报价单 → 议价中 → 成交 / 流失。流失要填原因。
3. 在询价页面按 **创建报价单**，加入明细；小计、折扣、税和总额会自动计算。
4. 在报价单页面：**打印 / PDF** 发给客户 → **标记为已发送** → **客户已接受** → **开发票**（发票或形式发票）。
5. 收到钱就在发票页面按 **记录收款**，发票会自动变成部分付款或「已付清」。逾期发票会显示在仪表板和铃铛里。
6. **客户 → 客户报表 / 销售员报表** 可以看每个客户或销售员的成交率和金额。

### 5. 项目
1. 在已成交的询价按 **转为项目**（或 **项目 → 新建项目**），填合同金额、预算成本、日期和负责人。
2. 项目页面里：
   - **概览**：发布现场进度更新和进度百分比。
   - **任务**：工作清单。
   - **成本**：材料 / 人工 / 分包 / 运输 / 设备 / 其他，对比预算。
   - **已领用库存**：从仓库领出的物料，由库存模块自动带入。
   - **单据**：上传图纸、照片、合约。
   - **历史**：谁改了什么。
3. 按 **发票** 为项目开发票。完工后可以 **发出保修** 或开 **服务单**。
4. **项目看板 / 项目日历 / 项目排程** 可以看所有项目的安排。

### 6. 库存和采购
- **库存主档**：物料编号、单位、补货点、各仓库的现有库存。点物料可以看库存卡（每一笔进出和结余）。
- **库存流水 → 记录出入库**：入库、出库、调整。用 **项目领料** 把材料计入项目成本，用 **调拨** 在仓库之间转移。库存不会低于零。
- **采购单**：新建草稿 → **标记为已下单** → 货到时按 **收货**（可以分批收）。收到的货会自动入库，并更新供应商的价格历史。
- **供应商**、**仓库**、**工人工价** 是基本资料。

### 7. 售后
- **保修**：从项目发出保修，打印 **保修证书**；客户来电时登记 **保修索赔**。
- **服务单**：安排上门时间，**日历** 显示本周的预约。
- **事故报告**：记录工地事故、严重程度和跟进。

### 8. 人事
- **我的考勤 / 我的请假**：每个员工只看到自己的记录。按 **申请请假**，选假期类型和日期；星期日和公假不算天数。
- **请假审批**（人事 / 经理）：每张申请按 **批准** 或 **拒绝**。
- **员工**：个人资料、薪资、银行、护照 / 准证 / CIDB / GDL 号码和到期日，以及上传的证件。
- **考勤看板**：今天谁已到、迟到、请假或还没打卡。人事可以补录或修改。**月度报表** 显示每个人的月度统计。
- **薪资**：**计算薪资** 选月份 → **生成草稿** → 检查并调整加班 / 佣金 / 扣款 → **定稿**（定稿后工资单会锁定，不能再改）。员工可以看到自己的工资单。
- **公假** 和 **公告** 在这里管理，会显示在每个人的仪表板上。

### 9. 成本
- **项目成本管理**：每个项目按类别对比预算和实际成本。
- **成本汇总报表**：按月份、项目或类别查看成本、开票收入和毛利，可以导出 CSV。

### 10. 系统（管理员）
- **用户**：建立登录账号，关联员工、分公司和角色，离职的人可以停用。
- **角色**：勾选每个角色可以查看或管理什么。管理员永远有全部权限。
- **设置**：公司名称、注册号、上班时间、迟到宽限分钟、SST 税率、报价有效天数、发票到期天数、默认报价条款；另有 **分公司**、**部门**、**假期类型** 分页。
- **审计日志 / 登录记录**：谁改了什么、谁登录过。

### 11. 我的资料
点右上角头像 → **我的资料** / **修改密码**。
- 可以改显示名称、昵称、手机、个人电邮、地址、紧急联系人和语言。
- 修改 **登录电邮** 需要输入当前密码。
- 新密码至少 8 位，要有字母和数字。
- 身份证、银行账户、薪资只能由人事修改。

## 开发者说明

| 路径 | 内容 |
|---|---|
| `config/erp.php` | 权限、默认角色、菜单 / Ctrl+K、服务 / 州属 / 单位列表 |
| `app/Services/StockService.php` | 唯一会改动库存数量的地方 |
| `app/Services/AttendanceService.php`、`StatutoryCalculator.php` | 考勤规则、EPF / SOCSO / EIS 估算 |
| `app/View/LayoutComposer.php` | 按权限过滤菜单、铃铛通知 |
| `public/css/app.css`、`public/js/app.js` | 设计系统（顶部是颜色变量）和原生 JS 小功能（`data-*`） |
| `resources/views/components/*` | Blade 组件：页头、状态标签、指标卡、表单栏、空状态、图标 |
| `lang/json/<模块>/zh_CN.json` | 中文翻译，用英文原文当 key，例如 `__('Customer')` |
| `database/seeders/DatabaseSeeder.php` | 演示数据 |

```bash
php artisan test        # 50 个功能测试，用内存 SQLite，不会动到 MySQL
```

新增页面：在 `routes/web.php` 加路由，在 `config/erp.php` 的 `menu` 加入菜单和所需权限，新文字用 `__('...')` 包起来，并在对应的 `zh_CN.json` 加中文。

## 注意事项
- **薪资的 EPF / SOCSO / EIS 是简化估算**，发薪前请对照 KWSP / PERKESO 官方表格。没有计算 PCB（所得税）。
- 给客户的打印文件（报价单、发票、保修证书）固定是英文；工资单跟随用户的语言。
- 用户自己输入的文字（客户名称、备注、公告）按原样显示，不会被翻译。
