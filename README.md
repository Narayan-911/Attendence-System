# CampusRoll: Professional College Attendance Management Platform

An enterprise-grade, role-based PHP and MySQL Attendance Management System designed for colleges and universities. Hosted on **AWS EC2** and connected to **AWS RDS MySQL**.

GitHub Repository: [https://github.com/Narayan-911/Attendance_System_Package](https://github.com/Narayan-911/Attendance_System_Package)

---

## 🏛️ System Architecture & Highlights

- **Role-Based Access Control (RBAC):** Server-side permission guards for **Admin**, **Teacher (Faculty)**, and **Student** roles.
- **Teacher Subject Authority:** Teachers can autonomously create, manage, edit, and safely archive their own academic subjects and courses without requiring administrative intervention.
- **Safe Archival & Zero Data Loss:** If historical attendance logs exist for a course, the system prohibits destructive deletion and safely archives the subject, preserving all student records.
- **Enterprise UI/UX:** Deep navy blue (`#0f172a`, `#1e293b`), crisp white cards, responsive sidebar navigation on desktop, and collapsible mobile drawer.
- **Interactive Attendance Sheet:** Instant student card roster with segmented toggle buttons (Present/Absent), real-time name/roll search, and bulk "All Present" shortcuts.
- **Reports & Excel Export:** Real-time filtering by course, date, and status with one-click UTF-8 BOM CSV exports.
- **AWS RDS Ready:** Native support for environment variables (`DB_HOST`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_PORT`) with safe local fallbacks and zero hardcoded secrets.

---

## 📁 Repository File Structure

```
Attendance_System_Package/
├── database_migration.sql        # Non-destructive SQL migration script for AWS RDS / Local
├── attendance.sql                # Complete initial production database schema
├── index.html                    # Modern landing page & portal entry
├── README.md                     # Comprehensive setup, deployment & architecture docs
├── css/
│   └── style.css                 # Enterprise navy design system, sidebar & component styling
├── js/
│   └── script.js                 # Reusable client scripts, input sanitization & alert handlers
└── php/
    ├── includes/
    │   ├── auth.php              # RBAC guards, session security, CSRF token management
    │   ├── header.php            # HTML document head, CSS links, CSRF meta
    │   ├── sidebar.php           # Role-aware navigation sidebar & mobile drawer
    │   └── footer.php            # Page footer & JavaScript initialization
    ├── db.php                    # AWS RDS dynamic connection & auto-migration engine
    ├── login.php                 # Secure login with session fixation defense
    ├── register.php              # Institutional account registration
    ├── dashboard.php             # Role-based analytical dashboards (Admin/Teacher/Student)
    ├── manage_subjects.php       # Teacher Subject Authority & management center
    ├── mark_attendance.php       # Interactive daily roll call & quick subject modal
    ├── view_attendance.php       # Attendance logs, search filters & Excel CSV export
    ├── add_student.php           # Student enrollment with roll number verification
    └── logout.php                # Secure session destruction & cookie cleanup
```

---

## 🔒 Security Best Practices

1. **Prepared Statements:** 100% of SQL queries utilize parameterized statements (`mysqli_stmt`) to eliminate SQL injection risks.
2. **CSRF Protection:** State-changing `POST` operations validate cryptographically secure tokens via `hash_equals()`.
3. **Session Hardening:** `session_regenerate_id(true)` executed upon successful authentication; session cookies enforce `HttpOnly` and `SameSite=Lax`.
4. **XSS Escaping:** Dynamic data rendered to the DOM is escaped using `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
5. **Safe Password Storage:** Passwords hashed using bcrypt via `password_hash()` and verified with `password_verify()`.

---

## 🚀 AWS RDS & EC2 Deployment Guide

### Step 1: Apply the Database Migration to AWS RDS MySQL
Connect to your AWS RDS MySQL instance using your EC2 terminal or MySQL client (e.g., MySQL Workbench, DBeaver, or EC2 CLI):

```bash
# Connect to your RDS MySQL database from EC2
mysql -h <your-rds-endpoint.amazonaws.com> -P 3306 -u <your-db-username> -p attendance_db < database_migration.sql
```

> [!NOTE]
> The `database_migration.sql` script is 100% non-destructive and idempotent. It uses `IF NOT EXISTS` queries and checks `INFORMATION_SCHEMA` before adding any column or table, preserving all existing attendance logs and student data.

---

### Step 2: Configure Environment Variables on AWS EC2
In your Apache/Nginx configuration or `/etc/environment` on your EC2 instance, define your RDS credentials:

```bash
# Example /etc/environment or Apache SetEnv:
sudo nano /etc/environment
```
Add the following lines (replace with your RDS details):
```ini
DB_HOST="your-rds-endpoint.c9xxxxxx.us-east-1.rds.amazonaws.com"
DB_USER="admin"
DB_PASSWORD="your_secure_rds_password"
DB_NAME="attendance_db"
DB_PORT="3306"
```
*(If you are running Apache, you can also add `SetEnv DB_HOST "..."` in your VirtualHost config).*

---

### Step 3: Pull Updates to AWS EC2
SSH into your AWS EC2 instance, navigate to your web root, and pull the latest code:

```bash
cd /var/www/html/attendance_system   # (or your project directory)
git pull origin master
sudo systemctl restart apache2       # or: sudo systemctl restart nginx
```

---

## ⏪ Rollback Procedure

If you ever need to revert to the previous state:

1. **Code Rollback:**
   ```bash
   git log --oneline -n 5
   git checkout <previous_commit_hash>
   ```
2. **Database Rollback:**
   Because all migrations were strictly additive (added `role` column with default `'teacher'` and the `subjects` table), existing queries continue to function even without reverting database columns. If you wish to remove the added `subjects` table:
   ```sql
   -- Only if rollback is strictly necessary:
   DROP TABLE IF EXISTS subjects;
   ```
   *(Existing `teachers`, `students`, and `attendance` tables remain completely unharmed).*

---

## 💻 Local Testing & Development

Run the built-in development server from this folder:
```bash
php -S localhost:8080
```
Open [http://localhost:8080](http://localhost:8080) in your browser.
