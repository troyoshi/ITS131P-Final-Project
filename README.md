# CDRC Relief Tracker — Front-End Prototype

**Project Title:** Implementing a Web-Based Disaster Relief Distribution Tracking System for Citizens' Disaster Response Center (CDRC)

**Course:** ITS131P — Information Management  
**Group:** Group 9  
**Activity:** Front-End Prototype (Pre-Final Activity)

---

## Group Members

| Name | Role / Contribution |
|------|---------------------|
| Carolyne A. | Database Design, ERD, Logical Data Model, SQL Script |
| Ben L. | System Analysis, Problem Identification, Impact Assessment |
| Francis G. | Target Users, System Scope Definition |

---

## System Description

The **CDRC Relief Tracker** is a web-based Disaster Relief Distribution Tracking System designed for the Citizens' Disaster Response Center (CDRC), a non-government organization in the Philippines that promotes community-based disaster management.

The system addresses CDRC's challenge of operating without a centralized digital platform by providing a unified web application for managing beneficiary registration, relief inventory, evacuation centers, distribution records, and reporting — all backed by a MySQL database.

---

## Pages Included

### Public Website
| File | Description |
|------|-------------|
| `index.html` | Homepage / Landing Page |
| `pages/about.html` | About CDRC & the system |
| `pages/features.html` | System features and scope |
| `pages/contact.html` | Contact info and form |

### System Mock-Up Pages
| File | Description |
|------|-------------|
| `pages/login.html` | Login page with role selector |
| `pages/dashboard.html` | Main admin dashboard with KPIs |
| `pages/records.html` | Beneficiary Records Management |
| `pages/reports.html` | Reports & Analytics page |
| `pages/profile.html` | User Profile & Settings |

---

## File Structure

```
cdrc-system/
├── index.html              # Homepage
├── README.md               # This file
├── css/
│   └── style.css           # All styles (design system)
├── js/
│   ├── main.js             # Interactivity & animations
│   └── nav.js              # Shared nav helper
└── pages/
    ├── about.html
    ├── features.html
    ├── contact.html
    ├── login.html
    ├── dashboard.html
    ├── records.html
    ├── reports.html
    └── profile.html
```

---

## Technical Notes

- **HTML5, CSS3, Vanilla JavaScript** — no frameworks required
- **Responsive** — adapts for desktop, tablet, and mobile
- **Google Fonts** used: Outfit (headings) + Inter (body)
- All navigation links are functional between pages
- Interactive elements: modal forms, tab switching, live search/filter, counter animations, login redirect
- Design system uses CSS custom properties (variables) for consistent theming

## Color Scheme

| Token | Color | Use |
|-------|-------|-----|
| `--navy` | `#0D2240` | Primary background, sidebar |
| `--orange` | `#E84B1A` | Alerts, CTAs, accent |
| `--teal` | `#009D8A` | Success, active states, secondary |

---

## How to Run

1. Extract the `.zip` file to a folder
2. Open `index.html` in any modern browser (Chrome, Firefox, Edge)
3. Use the navigation to explore all pages
4. Click **System Login** to access mock system pages
5. Demo credentials shown on the login page

> No server setup required — all pages are static HTML files.
