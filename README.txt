SIMPLE ERP SYSTEM - SETUP GUIDE

Requirements:
- XAMPP (Apache + MySQL)
- PHP with mysqli enabled

STEP 1: Copy folder
Copy the complete "Simple_ERP_System" folder into:
C:\xampp\htdocs\

STEP 2: Start XAMPP
Open XAMPP Control Panel.
Start Apache and MySQL.

STEP 3: Create database
Open:
http://localhost/phpmyadmin

Click Import -> Choose File -> select:
C:\xampp\htdocs\Simple_ERP_System\install.sql
Click Import / Go.

STEP 4: Open project
Visit:
http://localhost/Simple_ERP_System/

STEP 5: Add sample records
1. Open Products and add a product (example: Pen, 10, 100).
2. Open Customers and add a customer.
3. Open Employees and add an employee.
4. Open Sales and create a sale.

IMPORTANT:
- Keep the folder structure unchanged.
- Do not move product/customer PHP files separately. This project uses one index.php for all sections.
- Default XAMPP database login is root with an empty password. If your MySQL password is different, update config/database.php.
- This is a simple learning/demo project. Add authentication, backups, validation and deployment security before using it for real business.
