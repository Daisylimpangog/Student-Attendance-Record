# CHPCEBU Attendance Monitoring (Minimal Prototype)

This is a small PHP + MySQL attendance monitoring prototype intended to run under XAMPP.

Quick summary:
- PHP pages: `index.php` (login), `attendance.php` (mark/view), `admin.php` (admin listing), `logout.php`.
- DB: `init.sql` creates `users` and `attendance` tables and seeds an admin user.
- DB connection: `db.php` (uses PDO). Set DB credentials in `config.php`.
- Basic styling in `assets/css/style.css`.

How to run (Windows, XAMPP):
1. Copy the `CHPCEBU-Attendance` folder into `C:\xampp\htdocs` (if not already there).
2. Start Apache and MySQL via XAMPP Control Panel.
3. Create a database (e.g., `attendance_db`).
   - You can use phpMyAdmin: http://localhost/phpmyadmin
4. Import `init.sql` into the database.
5. Edit `config.php` to match database name/credentials if needed.
6. Open http://localhost/CHPCEBU-Attendance/ in your browser.

Seeded admin user (from `init.sql`):
- username: `admin@example.com`
- password: `Admin@123` (hashed in DB)

Security note: This is a prototype. Do not use default credentials on production. Use HTTPS, add CSRF protection, input validation and role checks before productionizing.

Next steps / improvements:
- Add registration and email verification.
- Add CSV export for attendance and filtering.
- Add mobile-friendly UI and client-side validations.
- Add unit/integration tests and CSRF tokens.
