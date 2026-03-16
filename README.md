# Buzznation Client Requirement Portal

A production-ready **Role Based Project Management Portal** built with Core PHP, MySQL, Bootstrap 5, jQuery, and AJAX.

---

## Deployment Guide

### 1. Clone / Upload files

Upload **all files** (including hidden `.htaccess` files) to your web-server document root, e.g.  
`/home/<user>/public_html/` for a cPanel shared-hosting account.

> The document root must point to the **repository root** (the folder that contains `index.php`).  
> Do **not** point it to the `public/` sub-folder.

### 2. Create the MySQL database & user

```sql
CREATE DATABASE buzz_sales_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'buzz_user'@'localhost' IDENTIFIED BY 'StrongPassword123!';
GRANT ALL PRIVILEGES ON buzz_sales_db.* TO 'buzz_user'@'localhost';
FLUSH PRIVILEGES;
```

Then import the schema:

```bash
mysql -u buzz_user -p buzz_sales_db < sql/schema.sql
```

### 3. Configure database credentials

Either set **environment variables** (recommended):

| Variable   | Default       | Description                  |
|------------|---------------|------------------------------|
| `DB_HOST`  | `localhost`   | MySQL hostname               |
| `DB_NAME`  | `buzz_sales_db` | Database name              |
| `DB_USER`  | `buzz_user`   | MySQL username               |
| `DB_PASS`  | *(empty)*     | MySQL password – **required**|

Or edit `config/database.php` directly and replace the `getenv()` fallback values.

> **Never** leave `DB_PASS` empty in production.

### 4. (Optional) Configure email notifications

Set the following environment variables to enable outbound email:

| Variable          | Description                       |
|-------------------|-----------------------------------|
| `SMTP_FROM_EMAIL` | Sender address                    |
| `SMTP_FROM_NAME`  | Sender display name               |

The application uses PHP's built-in `mail()` function.  For SMTP authentication  
(Gmail, SendGrid, etc.) replace the `sendEmail()` call in `config/email.php`  
with a library such as PHPMailer.

### 5. Directory permissions

The web-server user needs **write access** to the `uploads/` directory:

```bash
chmod 0750 uploads/
chmod 0750 logs/
```

### 6. Verify `.htaccess` files are applied

Ensure Apache has `AllowOverride All` (or at least `AllowOverride Options FileInfo`) for  
your document root.  The root `.htaccess` blocks direct access to `config/`, `lib/`,  
`sql/`, and `includes/` directories.

---

## Default Logins

| Role       | Username | Password    |
|------------|----------|-------------|
| Admin      | admin    | Admin@123   |
| Sales      | sales1   | Sales@123   |
| Design     | design1  | Design@123  |
| Operations | ops1     | Ops@1234    |

> **Change all passwords immediately after first login.**

---

## User Roles

| Role       | Description                                                      |
|------------|------------------------------------------------------------------|
| Admin      | Full control: user CRUD, activity logs, notification emails      |
| Sales      | Submit client requirement forms, manage projects, change requests|
| Design     | Review/approve/reject/hold projects, upload design files         |
| Operations | Cost estimation review and submission                            |

---

## Project Workflow

```
Sales submits form
  → Design: Approve / Reject / Hold
      → (Approved) Design uploads files
          → Operations: submit cost
              → Sales: review → Close  OR  submit Change Request
                  → (Change Request) → Design → Operations → Sales …
```

---

## Tech Stack

- Core PHP 8 (no frameworks)
- MySQL 5.7+ / MariaDB 10+
- Bootstrap 5.3
- jQuery 3.7
- AJAX
