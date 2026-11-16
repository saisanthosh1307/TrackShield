# TrackShield – Railway Emergency Management System

TrackShield is a web app for reporting, tracking and managing railway emergencies, built with PHP and MySQL.
Passengers report incidents, administrators verify, prioritise and assign them, and response teams handle them until they are resolved.

**Stack:** HTML5, CSS3, vanilla JavaScript · PHP 8.0+ (PDO) · MySQL 5.7+ / MariaDB 10.3+ · Chart.js 4.4.1 (bundled locally)

---

## Features

| Module | What it does |
|---|---|
| Authentication | Registration, login and logout, bcrypt hashing, password strength rules, session hardening, role-based access, login throttling |
| Passenger dashboard | Welcome banner, Report Emergency button, total / active / resolved / rejected counts, recent history, helplines, latest updates |
| Emergency reporting | 9 categories, title, description, station, train no. (optional), location, date and time, contact, photo evidence (optional), unique reference `TS-YYYYMMDD-XXXXXX` |
| Emergency tracking | 7 statuses, a visual progress tracker, a full timeline with remarks, the latest admin update and the assigned team's details |
| Admin dashboard | Total / new / pending verification / assigned / active operations / resolved, critical alerts, 14-day trend, status chart, category stats, recent reports |
| Emergency management | Search (reference, title, station, train, reporter), filters (status, priority, category, team, date range), sortable columns, pagination |
| Team management | Add / edit teams (each team gets a login), activate / deactivate, availability (Available / Busy / Off Duty), assign / reassign, active operations monitor |
| Priority | Critical / High / Medium. Each report gets an initial priority from its category, and admins can change it with a reason |
| Notifications | In-app notifications on submission, verification, assignment, every status change, team notes and resolution; unread badges |
| Reports & analytics | Daily and monthly charts, category statistics, resolved vs pending, priority split, team performance (resolved count, average resolution time), activity history, CSV export, print view |
| Users & categories | Activate / deactivate users, create admins, add / edit / delete categories (deletion blocked if the category is in use) |

## Status workflow

```
Submitted → Under Verification → Assigned → Response Team Dispatched → In Progress → Resolved
                     └──────────────→ Rejected (reason required)
```

* **Admin:** can set any status. The statuses Assigned, Dispatched and In Progress need an active team assignment.
* **Response team:** can set only Dispatched, In Progress or Resolved, and only on emergencies currently assigned to its team. Each update needs response notes ("actions taken").
* When an emergency is resolved or rejected, its assignment is closed and the team's availability updates automatically.

---

## Installation (XAMPP)

1. Copy the `TrackShield` folder into `C:\xampp\htdocs\`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin, go to **Import**, choose `database/trackshield_db.sql` and run it.
   The script creates the `trackshield_db` database, all tables and the seed data, so you don't need to create the database by hand.
4. Edit `config/database.php` if your MySQL credentials differ (XAMPP's default is `root` with no password).
   You can also set the environment variables `TS_DB_HOST`, `TS_DB_NAME`, `TS_DB_USER` and `TS_DB_PASS`.
5. Open http://localhost/TrackShield/

### Default accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@trackshield.local` | `Admin@123` |
| Response team (Central Rescue Unit) | `rescue@trackshield.local` | `Team@123` |
| Passenger | register at `/register.php` | — |

> **Change these passwords right after the first login.** Edit the team password in *Response Teams → Edit*. For the admin, create a new administrator in *Users* and deactivate the seeded one.

---

## Folder structure

```
TrackShield/
├── index.php  login.php  register.php  logout.php
├── config/database.php           DB credentials + PDO connection (web access denied)
├── includes/
│   ├── header.php footer.php navbar.php   layout, sidebar, toasts, confirm modal
│   ├── auth.php                  sessions, RBAC (require_role), login/logout
│   ├── functions.php             escaping, CSRF, validation, uploads, status logic, notifications
│   ├── emergency-view.php        shared detail view (tracker, details, team, timeline)
│   └── notifications-view.php    shared notifications page
├── assets/css/  style.css  dashboard.css  responsive.css
├── assets/js/   main.js  validation.js  dashboard.js  vendor/chart.umd.min.js
├── uploads/emergencies/          incident photos (script execution disabled)
├── passenger/  dashboard, report-emergency, my-emergencies, emergency-details, notifications
├── admin/      dashboard, emergencies, emergency-details, assign-team, manage-teams,
│               manage-users, categories, reports, notifications
├── response/   dashboard, assigned-emergencies, emergency-details, update-status, notifications
└── database/trackshield_db.sql
```

### Database (`trackshield_db`)

`users`, `emergency_categories`, `emergencies`, `response_teams`, `emergency_assignments`, `emergency_updates` and `notifications`, with InnoDB foreign keys, unique keys and indexes.

The schema adds a few columns beyond the specification:

* `response_teams.user_id` links a team to its login account.
* `emergencies.incident_datetime` stores the reported date and time of the incident.
* `emergency_assignments.completed_at` is used to measure team performance.

---

## Security

* `password_hash()` / `password_verify()` with automatic rehash, and a strong password policy.
* PDO prepared statements everywhere, with emulated prepares off. Sort columns come from a whitelist.
* All output is escaped with `htmlspecialchars` through `e()`, and chart data is embedded with `JSON_HEX_TAG`.
* A CSRF token on every POST form, including logout. Comparison uses `hash_equals`.
* Role checks on every page. Passengers can see only their own reports, and teams only their own assignments (no IDOR).
* Session hardening: HttpOnly, SameSite and Secure-on-HTTPS cookies, strict mode, ID regeneration on login, a 30-minute idle timeout, and immediate logout of deactivated accounts.
* Uploads are checked by real MIME type (`finfo` + `getimagesize`), limited to JPG, PNG, GIF and WEBP up to 5 MB, and saved under random 32-hex-character names. Script execution in `uploads/` is blocked with `.htaccess`.
* `.htaccess` denies web access to `config/`, `includes/` and `database/`, disables directory listing, and sets security headers.
* CSV export neutralises spreadsheet formula injection.
* Login throttling: 5 failed attempts per session lock login for 15 minutes.

## Production deployment checklist

1. Import the SQL file on the production server, create a dedicated MySQL user with privileges only on `trackshield_db`, and put its credentials in `config/database.php` (or in environment variables).
2. Keep `APP_DEBUG` set to `false`. Set `APP_TIMEZONE`.
3. Serve the site over HTTPS. Secure cookies turn on automatically.
4. Make sure Apache has `AllowOverride All`, so the `.htaccess` rules apply, and `mod_headers` enabled. On Nginx, add the equivalent deny rules for `config/`, `includes/`, `database/` and for scripts in `uploads/`.
5. Make `uploads/emergencies/` writable by the web server only.
6. Change or remove the seeded demo accounts.

## Future enhancements

GPS-based live location and an interactive railway map, SMS and email alerts, automatic or AI-assisted prioritisation and classification, integration with railway authority systems, a mobile app, live team tracking, and route-level analytics.
