````markdown
# E-Learning Platform — PHP + MySQL MVP

This repository contains a starter scaffold for an e-learning platform built with PHP, MySQL, HTML, CSS and JavaScript.

Overview
- Roles: admin, instructor, student
- Minimal MVP features included:
	- User registration and login (role-aware)
	- Course listing and basic CRUD for admin/instructor
	- Enrollment and simple progress tracking
	- Database schema file (db/schema.sql)



https://github.com/user-attachments/assets/8d93317d-ce64-4d8d-bc8f-f4895d68e5d6



Requirements
- PHP 7.4+ with PDO/MySQL
- MySQL (or MariaDB)
- A webserver like Apache (XAMPP/WAMP) or PHP built-in server for development

Quick setup (Windows / PowerShell)
1. Start MySQL (e.g., via XAMPP/WAMP).
2. Create a database, e.g. `e_learning`.
3. Import the schema: `mysql -u root -p e_learning < db\\schema.sql`.
4. Copy `includes/config.example.php` to `includes/config.php` and set DB credentials.
5. Run the built-in server for quick testing (from project root):

```powershell
php -S localhost:8000 -t public
```

Open http://localhost:8000 in your browser.

What's included
- `db/schema.sql` — MySQL schema and sample data insertion guidance
- `includes/config.example.php` — copy to `includes/config.php` and fill values
- `includes/db.php`, `includes/auth.php` — DB connection and auth helpers
- `public/` — public-facing PHP pages (index, login, register, dashboard)
- `assets/` — basic CSS and JS

Next steps
- Implement payment integration (Stripe/PayPal)
- Add file upload handling for course materials
- Implement detailed reporting, certificates, forums
- Add tests and CI

````
