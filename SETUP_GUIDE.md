# CDRC Relief Tracker — Installation & Setup Guide

## System Architecture

- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Backend:** PHP 8.0+
- **Database:** MySQL 8.0+
- **Server:** Apache (XAMPP/WAMP recommended for local development)

---

## Prerequisites

1. **XAMPP or WAMP** installed and running (Apache + MySQL)
   - Download: https://www.apachefriends.org/
2. **PHP 8.0 or higher**
3. **MySQL 8.0 or higher**
4. **A modern web browser** (Chrome, Firefox, Edge)

---

## Installation Steps

### Step 1: Extract Project Files

```bash
# Extract cdrc-relief-tracker.zip to:
# Windows: C:\xampp\htdocs\cdrc-system
# Mac/Linux: /Applications/XAMPP/htdocs/cdrc-system
```

### Step 2: Create Database

1. Open **phpMyAdmin**:
   - Navigate to `http://localhost/phpmyadmin`
   - Login with default credentials (usually username: `root`, password: empty)

2. **Import the SQL script**:
   - Click **Import** tab
   - Select `cdrc-system/cdrc_database.sql`
   - Click **Go** to execute

   **Or via MySQL CLI**:
   ```bash
   mysql -u root -p < /path/to/cdrc_database.sql
   ```

### Step 3: Configure Database Connection

Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');      // MySQL server host
define('DB_NAME', 'cdrc_relief_tracker');  // Database name
define('DB_USER', 'root');           // MySQL username
define('DB_PASS', '');               // MySQL password (empty by default in XAMPP)
define('SITE_URL', 'http://localhost/cdrc-system');
```

### Step 4: Start Services

**Windows (XAMPP)**:
- Open XAMPP Control Panel
- Click **Start** next to Apache
- Click **Start** next to MySQL

**Mac/Linux**:
```bash
sudo /Applications/XAMPP/bin/xampp start
```

### Step 5: Access the Application

Navigate to:
```
http://localhost/cdrc-system
```

---

## Login Credentials (Pre-Loaded)

All demo users have password: `Password123!`

| Role | Username | Email |
|------|----------|-------|
| **Administrator** | juan.admin | juan.admin@cdrc.org.ph |
| **Staff** | ana.reyes | ana.reyes@cdrc.org.ph |
| **Staff** | ben.lim | ben.lim@cdrc.org.ph |
| **Volunteer** | francis.go | francis.go@cdrc.org.ph |
| **Volunteer** | carolyne.abad | carolyne.abad@cdrc.org.ph |

---

## Project Structure

```
cdrc-system/
├── index.html                    # Public homepage
├── README.md                      # Project info
├── cdrc_database.sql             # Database schema + sample data
│
├── includes/
│   └── config.php                # Database config & helpers
│
├── api/
│   ├── auth.php                  # Login/logout endpoints
│   ├── beneficiaries.php         # Beneficiary CRUD
│   ├── inventory.php             # Relief items & transactions
│   ├── distributions.php         # Distribution records
│   ├── centers.php               # Evacuation centers
│   ├── dashboard.php             # KPIs & activity feed
│   ├── reports.php               # Report generation
│   └── users.php                 # User management
│
├── css/
│   └── style.css                 # All styles (design system)
│
├── js/
│   ├── main.js                   # Client-side interactivity
│   └── nav.js                    # Shared nav/footer helper
│
├── pages/
│   ├── login.php                 # DB-driven login
│   ├── dashboard.php             # DB-driven dashboard
│   ├── records.php               # Beneficiary records (template)
│   ├── inventory.php             # Inventory management (template)
│   ├── centers.php               # Evacuation centers (template)
│   ├── distributions.php         # Distribution records (template)
│   ├── reports.php               # Reports (template)
│   ├── users.php                 # User management (template)
│   ├── profile.php               # User profile (template)
│   └── [HTML versions still available]
│
├── assets/
│   └── images/                   # (for future use)
```

---

## API Endpoints Reference

All endpoints require authentication (session-based login).

### Authentication
- `POST /api/auth.php?action=login` — Login
- `POST /api/auth.php?action=logout` — Logout
- `GET /api/auth.php?action=me` — Current user

### Beneficiaries
- `GET /api/beneficiaries.php?action=list` — List with search/filter
- `GET /api/beneficiaries.php?action=get&id=1` — Get single
- `POST /api/beneficiaries.php?action=create` — Create
- `POST /api/beneficiaries.php?action=update&id=1` — Update
- `POST /api/beneficiaries.php?action=delete&id=1` — Delete (admin only)

### Inventory
- `GET /api/inventory.php?action=list` — List items
- `GET /api/inventory.php?action=categories` — Get item categories
- `GET /api/inventory.php?action=transactions&id=1` — Item history
- `GET /api/inventory.php?action=low_stock` — Low stock alert items
- `POST /api/inventory.php?action=stock_in` — Add stock
- `POST /api/inventory.php?action=stock_out` — Deduct stock

### Dashboard
- `GET /api/dashboard.php?action=kpis` — KPI metrics
- `GET /api/dashboard.php?action=activity` — Activity feed
- `GET /api/dashboard.php?action=weekly_chart` — Distribution chart

### Reports
- `GET /api/reports.php?action=distribution&date_from=2025-06-01&date_to=2025-06-30`
- `GET /api/reports.php?action=beneficiary`
- `GET /api/reports.php?action=inventory`

### Evacuation Centers
- `GET /api/centers.php?action=list` — List centers
- `GET /api/centers.php?action=get&id=1` — Get single center

### Users
- `GET /api/users.php?action=list` — List users (admin only)
- `GET /api/users.php?action=profile` — Current user profile
- `POST /api/users.php?action=update_password` — Change password

---

## Database Schema Overview

### Core Tables
1. **roles** — User roles (Admin, Staff, Volunteer)
2. **users** — System users
3. **evacuation_centers** — Relief distribution points
4. **beneficiaries** — Disaster-affected families
5. **relief_items** — Master list of relief goods
6. **item_categories** — Item grouping (Food, Water, Medicine, etc.)
7. **inventory_transactions** — Stock movements (IN/OUT)
8. **distribution_records** — Relief distribution events
9. **distribution_items** — Line items per distribution
10. **special_needs_types** — Beneficiary special needs

### Relationships
- Users have Roles (1:N)
- Beneficiaries assigned to Evacuation Centers (N:1)
- Relief Items in Categories (N:1)
- Distributions link Beneficiaries + Items (N:N via junction table)
- Inventory Transactions track all stock movements

---

## Testing the System

### 1. Test Login
```
URL: http://localhost/cdrc-system/pages/login.php
Username: juan.admin
Password: Password123!
```

### 2. Test Beneficiary CRUD via API
```bash
# List beneficiaries (with curl)
curl -b "PHPSESSID=xxx" \
  "http://localhost/cdrc-system/api/beneficiaries.php?action=list"

# Create beneficiary
curl -b "PHPSESSID=xxx" -X POST \
  -H "Content-Type: application/json" \
  -d '{"first_name":"Test","last_name":"User","household_size":4,"address":"Test Address","barangay":"Masaya","center_id":1}' \
  "http://localhost/cdrc-system/api/beneficiaries.php?action=create"
```

### 3. Test Dashboard
```
URL: http://localhost/cdrc-system/pages/dashboard.php
(Should load KPIs from database)
```

---

## Troubleshooting

### "Database connection failed"
- Check MySQL is running
- Verify credentials in `includes/config.php`
- Ensure database `cdrc_relief_tracker` exists

### "404 Not Found" on API calls
- Check URL structure: `/api/beneficiaries.php?action=list`
- Verify file exists in `api/` folder
- Check PHP is enabled on your server

### "Session error" or "Not authenticated"
- Ensure cookies are enabled in browser
- Try clearing browser cache
- Log out and log in again

### "Access Denied" on delete/update
- Check user role — only Admins can delete
- Staff can create/update but not delete
- Volunteers have read-only access

---

## Next Steps

### To Complete the System:
1. ✅ Database design & schema
2. ✅ API endpoints for all modules
3. ⏳ Frontend pages (records.php, inventory.php, reports.php, etc.) — Currently using static HTML, can be converted to PHP
4. ⏳ Advanced features (export to PDF, email notifications, SMS alerts)

### To Deploy to Production:
1. Use a managed hosting provider (Bluehost, HostGator, etc.)
2. Upload files via FTP
3. Create MySQL database via hosting control panel
4. Update `includes/config.php` with production credentials
5. Implement HTTPS/SSL certificate
6. Set up automated backups

---

## Support & Documentation

- **ERD Diagram:** See `Data Dictionary.xlsx` (ERD sheet)
- **Database Schema:** `cdrc_database.sql`
- **API Reference:** Documentation above
- **Frontend Documentation:** Check `README.md`

For issues or questions, refer to the inline comments in PHP files.

---

**Last Updated:** June 2025  
**Version:** 1.0 (Beta)  
**Course:** ITS131P — Information Management  
**Group:** Group 9
