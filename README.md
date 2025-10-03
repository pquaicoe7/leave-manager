# Leave Manager (PHP + MySQL)

Small demo to manage employee leave with admin/employee roles.

## Features
- Admin: create leave types, assign types to employees, review requests (approve/reject with reason).
- Employee: apply for leave (only assigned types, no past dates, overlap check, respects max days), see status & rejection reason.

## Tech & Why
- PHP 8 (fast to build server-rendered forms, simple sessions)
- MySQL (relational: foreign keys, unique constraints, transactions)
- Bootstrap 5 for styling

## Run locally (XAMPP on port 8080)
1. Start **Apache** (8080) and **MySQL**.
2. Create DB **`eban_leave_db`** (utf8mb4) and import **`db.sql`**.
3. Visit **`http://localhost:8080/eban-leave/seed.php`** (creates demo users).
4. App entry: **`http://localhost:8080/eban-leave/public/`**

### Demo accounts
- Admin: `admin@example.com` / `admin123`
- Employee: `jane@example.com` / `employee123`

## Security basics
- Sessions + role guard `require_role('admin'|'employee')` on every protected page.
- Prepared statements everywhere; passwords hashed; output escaped.
