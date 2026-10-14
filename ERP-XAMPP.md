# ERP System v2（XAMPP 本地运行）

1. 启动 XAMPP 的 Apache + MySQL（MariaDB 端口 **3307**，root 无密码）
2. 数据库 `erp` 已存在；重建演示数据：`cd C:\xampp\htdocs\erp` → `php artisan migrate:fresh --seed`
   （⚠ 会清空 erp 数据库，只在测试阶段用）
3. 打开 http://localhost/erp
4. 演示账号（密码都是 `password`）：

| 账号 | 角色 | 能看到 |
|---|---|---|
| admin@erp.local | Admin | 全部 |
| manager@erp.local | Manager | 除用户/角色/薪资外全部 |
| sales@erp.local | Salesman | 客户、询价、报价、发票 |
| supervisor@erp.local | Site Supervisor | 项目、售后、库存查看 |
| store@erp.local | Storekeeper | 库存、采购 |
| hr@erp.local | HR & Admin | 员工、考勤、请假、薪资 |
| accounts@erp.local | Accounts | 发票、收款、成本 |
| staff@erp.local | Staff | 只有自己的打卡、请假 |

测试：`php artisan test`（用内存 sqlite，不会动到 MySQL 数据）。
