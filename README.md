# KCALS 🥗

**Smart Nutrition & Wellness App — v0.8.0 Beta**

A modern PHP + MySQL wellness web app that delivers personalised weekly meal plans using the **Mifflin-St Jeor** algorithm, psychological zone tracking, and progress monitoring.

---

## Features (Phase 1 MVP)

| Feature | Status |
|---|---|
| BMR / TDEE Calculator (Mifflin-St Jeor) | ✅ |
| 3 Psychological Zones (Green / Yellow / Red) | ✅ |
| 7-Day Meal Plan Generator | ✅ |
| Daily Check-in (weight, stress, motivation) | ✅ |
| Weight Trend Chart (Chart.js) | ✅ |
| Progress History Table | ✅ |
| Wellness Tips (categorised) | ✅ |
| Shopping List Generator (printable) | ✅ |
| CSRF Protection + Password Hashing | ✅ |
| Mobile-First Responsive UI | ✅ |

---

## Tech Stack

- **Backend:** Pure PHP 8+ (no framework)
- **Database:** MySQL 8 / MariaDB
- **Frontend:** Vanilla HTML/CSS/JS, Chart.js, Lucide Icons
- **Fonts:** Inter (Google Fonts)

---

## Local Setup (XAMPP)

### 1. Clone into htdocs
```bash
cd C:/xampp/htdocs
git clone https://github.com/TheoSfak/kcals kcals
```

### 2. Create the database
Start XAMPP (Apache + MySQL), then in a terminal:
```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS kcals CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\xampp\mysql\bin\mysql.exe -u root kcals < C:\xampp\htdocs\kcals\sql\schema.sql
```

### 3. Configure DB
```bash
copy C:\xampp\htdocs\kcals\config\db.php.example C:\xampp\htdocs\kcals\config\db.php
```
Edit `config/db.php` and set your MySQL credentials (default XAMPP: user=`root`, pass=`""`).

### 4. Open in browser
```
http://localhost/kcals/
```

---

## Colour Palette

| Role | Hex |
|---|---|
| Primary (Mint Green) | `#2ECC71` |
| Dark Green | `#27AE60` |
| Dark Slate (text) | `#2C3E50` |
| Background | `#F7F9FC` |

---

## Changelog

### v0.8.0-beta — 2026-04-20
- Initial MVP release
- Full auth system (register / login / logout) with CSRF & bcrypt
- Mifflin-St Jeor BMR + TDEE engine
- Psychological zone system (Green 25% / Yellow 15% / Red 8% deficit)
- 7-day meal plan generator with seasonal + diet-type filtering
- Daily check-in with sliders
- Weight trend chart
- Progress history page
- Wellness tips page with category filter
- Shopping list generator with print support
- 20 seed recipes across all meal categories
- 12 health & wellness tips

---

## Roadmap

- **v0.9:** Social Buffer (weekend calorie shift) + Plateau Breaker algorithm
- **v1.0:** Seasonal recipe logic + Shopping List PDF export
- **v1.1:** Premium features + affiliate monetization layer
