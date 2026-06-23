# Elite 2.0 — Martial Arts Academy Management Platform

A clean, API-driven academy management system for martial arts schools.  
PHP 8.1+ · MySQL/MariaDB · Vanilla JS (ES Modules) · No frameworks, no build step.

---

## Architecture

```
elite2/
├── api/
│   └── index.php          # Single API entry point — all routes
├── config.php             # Env loader, constants, error handler
├── core/
│   ├── Auth.php           # JWT issue/verify, password hashing
│   ├── Controller.php     # Base controller (body, query, auth helpers)
│   ├── Database.php       # PDO singleton
│   ├── Response.php       # JSON response helpers
│   └── Router.php         # Lightweight REST router
├── controllers/
│   ├── AuthController.php        # Login, logout, refresh, register, password
│   ├── AdminController.php       # Users, disciplines, belts, belt skills, coaches
│   ├── StudentController.php     # Student CRUD, attendance, eligibility, points, notes
│   ├── CoachController.php       # Classes, attendance sessions, evaluations, skills, promotions, analytics
│   └── NotificationController.php# Inbox, mark-read, broadcast
├── middleware/
│   ├── AuthMiddleware.php         # JWT required guard
│   └── RoleMiddleware.php         # Role-based guard
├── database/
│   ├── schema.sql                 # Full schema with seeds
│   └── seed_admin.php             # Creates first admin user
├── public/
│   ├── index.html                 # Root redirect
│   ├── login.html
│   ├── css/
│   │   ├── tokens.css             # Design tokens (CSS custom properties)
│   │   └── styles.css             # Full component library — no inline styles in HTML
│   ├── js/
│   │   ├── api.js                 # REST client with auto token-refresh
│   │   ├── ui.js                  # Toast, modal, skeleton, avatar, badge helpers
│   │   ├── shell.js               # Sidebar, topbar, nav highlighting
│   │   └── auth-guard.js          # Client-side route protection
│   └── pages/
│       ├── admin-dashboard.html
│       ├── hc-dashboard.html      # Head Coach
│       ├── coach-dashboard.html
│       ├── student-dashboard.html
│       ├── students.html          # List, search, create, view modal
│       ├── coaches.html           # Coach cards + student assignment
│       ├── disciplines.html       # Curriculum: disciplines → belts → skills
│       ├── evaluations.html       # Score + view evaluations
│       ├── attendance.html        # Session management + bulk mark
│       ├── belt-progress.html     # Eligibility checks + skill checklist
│       ├── promotions.html
│       ├── seminars.html
│       ├── schedule.html          # Weekly timetable
│       ├── notifications.html     # Inbox + broadcast
│       ├── reports.html
│       ├── my-progress.html       # Student evaluation history
│       ├── my-attendance.html
│       ├── achievements.html      # Points ledger + badges
│       ├── profile.html
│       ├── settings.html
│       ├── users.html             # Admin user management
│       └── forgot-password.html
└── .htaccess                      # Route /api/* + SPA fallback
```

---

## Setup

### Requirements
- PHP 8.1+
- MySQL 8+ or MariaDB 10.6+
- Apache with `mod_rewrite` (XAMPP works out of the box)
- Composer

### 1. Clone & install

```bash
git clone https://github.com/BT-Rajan/elite2.git
cd elite2
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
```

Edit `.env`:

```
DB_HOST=127.0.0.1
DB_NAME=elite2
DB_USER=root
DB_PASS=

# Generate: php -r "echo bin2hex(random_bytes(32));"
JWT_SECRET=your_64_char_random_string_here
```

### 3. Create database & run schema

```sql
CREATE DATABASE elite2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u root -p elite2 < database/schema.sql
```

### 4. Seed the admin user

```bash
php database/seed_admin.php
```

Default credentials (change immediately after first login):
- Email: `admin@elite2.com`
- Password: `Admin@1234!`

### 5. Configure Apache / XAMPP

Point your virtual host document root to the `elite2/` directory.  
`mod_rewrite` must be enabled. The `.htaccess` handles all routing.

If running on XAMPP locally, place the project at `C:\xampp\htdocs\elite2\` and visit `http://localhost/elite2/`.

---

## API

All API routes live under `/api/`. Full REST — JSON in, JSON out.

### Auth (public)
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/login` | Login → access + refresh tokens |
| POST | `/api/auth/refresh` | Rotate tokens |
| POST | `/api/auth/forgot-password` | Send reset email |
| POST | `/api/auth/reset-password` | Complete reset |

### Auth (Bearer token required)
| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/logout` | Revoke refresh token |
| GET | `/api/auth/me` | Current user |
| PATCH | `/api/auth/me` | Update profile |
| POST | `/api/auth/change-password` | Change password |
| POST | `/api/auth/register` | Create user (admin/head_coach) |

### Students, Classes, Attendance, Evaluations, Promotions, Seminars, Analytics, Admin, Notifications
See `api/index.php` for the complete route list (60+ endpoints).

---

## Roles

| Role | Access |
|------|--------|
| `admin` | Full system access |
| `head_coach` | Students, coaches, curriculum, promotions, reports |
| `coach` | Assigned students, attendance, evaluations |
| `student` | Own dashboard, progress, belt status |

---

## Design System

All visual styling is centralised:

- **`public/css/tokens.css`** — CSS custom properties for colour, spacing, typography, radius, shadow, z-index
- **`public/css/styles.css`** — Component classes: `.btn`, `.card`, `.stat-card`, `.badge`, `.progress-bar`, `.modal`, `.toast`, `.avatar`, `.input`, `.select`, `.skeleton`, and utility classes

**No inline styles in HTML.** All pages use only class names from `styles.css`.

---

## Security

- JWT access tokens (15 min) + rotating refresh tokens (7 days)
- `password_hash(BCRYPT, cost=12)` for all passwords
- Prepared statements everywhere — no raw SQL interpolation
- Role enforcement on every protected endpoint
- Constant-time comparison on login to prevent user enumeration
- JWT secret length guard at startup

---

## Removed (not in scope for v1)

Parent portal · Family billing · AI features · Google Calendar · Stripe · PWA offline · SMS · Multi-facility · Redis · S3 · Complex notification preferences

