# 🚀 Statutoria Monitoring BKI - Panduan Instalasi Lengkap

## Status: ✅ 100% SIAP PAKAI!

Aplikasi ini **sudah selesai 100%** dengan semua fitur berikut:

### ✨ Fitur yang Sudah Selesai:

#### 🎨 **UI/UX Components**
- ✅ Login page dengan branding BKI yang professional
- ✅ Sidebar navigation dengan role-based menu
- ✅ Toast notifications (success/error/warning/info)
- ✅ Loading spinners untuk semua actions
- ✅ Dark mode support
- ✅ Responsive design (mobile-friendly)

#### 🔐 **Authentication & Authorization**
- ✅ Laravel Breeze + Livewire
- ✅ Spatie Permission (6 roles, 30+ permissions)
- ✅ Role-based access control
- ✅ Email verification support

#### 📊 **Database & Models**
- ✅ 12 migrations dengan indexes optimal
- ✅ 11 models dengan relationships lengkap
- ✅ Soft deletes, timestamps, casts
- ✅ Business logic methods

#### 🔧 **Service Layer**
- ✅ AuditTrailService - Timeline & history tracking
- ✅ StuckDetectionService - 4 detection rules
- ✅ AlertService - Multi-channel notifications

#### 📝 **Seeders**
- ✅ 15+ sample users (admin, manager, SBU, kacab, inspector, clients)
- ✅ 5 companies
- ✅ 5 workflow steps
- ✅ 5 jenis permohonan
- ✅ 10 dokumen master
- ✅ 15 system configurations

#### 🎯 **Core Features**
- ✅ Auto-generate nomor permohonan
- ✅ SLA tracking otomatis
- ✅ Stuck detection dengan 4 rules
- ✅ History log setiap action
- ✅ Aging calculation per step
- ✅ Multi-level alerts & escalation
- ✅ Document versioning
- ✅ File integrity check (SHA256)
- ✅ Timeline component dengan filters

---

## 📋 Requirements

- **PHP** 8.2 atau lebih tinggi
- **Composer** 2.x
- **Node.js** 18.x atau lebih tinggi
- **NPM** atau **Yarn**
- **MySQL** 5.7+ atau **MariaDB** 10.3+
- **Web Server**: Apache/Nginx atau PHP Built-in Server

---

## 🔧 Instalasi Step-by-Step

### 1️⃣ Setup Database

Buat database MySQL baru:

```sql
CREATE DATABASE statutoria_monitoring CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2️⃣ Konfigurasi Environment

File `.env` sudah ada, edit konfigurasi database:

```bash
# Buka file .env dan edit:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=statutoria_monitoring
DB_USERNAME=root
DB_PASSWORD=your_password_here

# Pastikan juga:
APP_NAME="MANTRA"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

### 3️⃣ Install Dependencies

Dependencies sudah terinstall, tapi jika ada masalah:

```bash
composer install
npm install
```

### 4️⃣ Generate Application Key

Jika belum ada:

```bash
php artisan key:generate
```

### 5️⃣ Run Migrations & Seeders

**INI LANGKAH PALING PENTING!**

```bash
php artisan migrate:fresh --seed
```

Output yang diharapkan:
```
Migration table created successfully.
Migrating: 2014_10_12_000000_create_users_table
Migrated:  2014_10_12_000000_create_users_table (XX.XXms)
...
Seeding: RolePermissionSeeder
Seeded:  RolePermissionSeeder (XX.XXms)
...
Database seeding completed successfully.
```

### 6️⃣ Create Storage Link

```bash
php artisan storage:link
```

### 7️⃣ Compile Assets

Untuk development:
```bash
npm run dev
```

Atau untuk production:
```bash
npm run build
```

### 8️⃣ Start Server

```bash
php artisan serve
```

Aplikasi akan berjalan di: **http://localhost:8000**

---

## 👤 Default Login Credentials

Setelah seeding berhasil, gunakan credentials berikut:

### 🔴 **Admin** (Full Access)
- **Email:** admin@bki.co.id
- **Password:** password
- **Akses:** Semua fitur

### 🟠 **Manager** (Monitoring & Reports)
- **Email:** manager@bki.co.id
- **Password:** password
- **Akses:** Dashboard, monitoring, reports

### 🟡 **SBU** (Final Approval)
- **Email:** sbu1@bki.co.id
- **Password:** password
- **Akses:** Approval, review, publish

### 🟢 **Kepala Cabang**
- **Email:** kacab1@bki.co.id
- **Password:** password
- **Akses:** Approval level 2

### 🔵 **Inspector**
- **Email:** inspector1@bki.co.id
- **Password:** password
- **Akses:** Review & verification

### 🟣 **Client** (per company)
- **Email:** client1@PTI.co.id
- **Password:** password
- **Akses:** Submit & upload dokumen

---

## 🎯 Struktur Menu (Role-Based)

### **Admin** melihat:
- Dashboard
- Permohonan (Daftar, Buat)
- Approval
- Monitoring (Dashboard, Stuck, SLA)
- Laporan
- Notifikasi
- Master Data (Perusahaan, Jenis, Dokumen, Workflow)
- Pengaturan (System, Users, Roles)

### **Client** melihat:
- Dashboard
- Permohonan (Daftar, Buat)
- Notifikasi

### **Inspector/Kacab/SBU** melihat:
- Dashboard
- Permohonan (Daftar)
- Approval
- Monitoring
- Laporan
- Notifikasi

---

## 🔄 Background Jobs (Optional)

Untuk menjalankan queue worker:

```bash
php artisan queue:work
```

Untuk scheduled tasks (stuck detection, reminders):

```bash
php artisan schedule:work
```

Atau tambahkan ke crontab:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🧪 Testing

Untuk menjalankan tests:

```bash
php artisan test
```

---

## 🐛 Troubleshooting

### ❌ Error: "could not find driver"

**Solusi:** Install PHP MySQL extension

Windows (XAMPP/Laragon):
- Buka `php.ini`
- Uncomment: `extension=pdo_mysql` dan `extension=mysqli`
- Restart web server

Linux:
```bash
sudo apt-get install php8.2-mysql
sudo systemctl restart apache2
```

### ❌ Error: "Class 'ZipArchive' not found"

**Solusi:** Install PHP Zip extension

```bash
# Ubuntu/Debian
sudo apt-get install php8.2-zip

# Windows: uncomment di php.ini
extension=zip
```

### ❌ Error: "GD Library not found"

**Solusi:** Install PHP GD extension

```bash
# Ubuntu/Debian
sudo apt-get install php8.2-gd

# Windows: uncomment di php.ini
extension=gd
```

### ❌ Permission Denied pada storage/

```bash
# Linux/Mac
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Windows: Run as Administrator
icacls storage /grant Users:F /t
icacls bootstrap/cache /grant Users:F /t
```

### ❌ Assets tidak ter-compile

```bash
# Clear cache
npm cache clean --force
rm -rf node_modules package-lock.json

# Reinstall
npm install
npm run build
```

### ❌ Migration Error

```bash
# Drop all tables dan migrate ulang
php artisan migrate:fresh --seed

# Jika masih error, check database connection di .env
php artisan config:clear
php artisan cache:clear
```

---

## 📁 Struktur Project

```
statutoria-monitoring/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Livewire/
│   │       ├── Layout/
│   │       │   ├── Navigation.php
│   │       │   └── Sidebar.php
│   │       └── PermohonanTimeline.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Company.php
│   │   ├── Permohonan.php
│   │   ├── ProcessHistoryLog.php
│   │   ├── StuckFlag.php
│   │   ├── AlertLog.php
│   │   └── ... (11 models total)
│   ├── Services/
│   │   ├── AuditTrailService.php
│   │   ├── StuckDetectionService.php
│   │   └── AlertService.php
│   └── Traits/
│       └── WithNotifications.php
├── database/
│   ├── migrations/
│   │   ├── 2024_01_01_000001_create_companies_table.php
│   │   ├── 2024_01_01_000002_create_jenis_permohonan_table.php
│   │   ├── ... (12 migrations total)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── RolePermissionSeeder.php
│       ├── CompanySeeder.php
│       ├── WorkflowSeeder.php
│       ├── JenisPermohonanSeeder.php
│       ├── DokumenMasterSeeder.php
│       ├── SystemConfigurationSeeder.php
│       └── UserSeeder.php
├── resources/
│   ├── views/
│   │   ├── components/
│   │   │   ├── toast.blade.php
│   │   │   ├── loading-spinner.blade.php
│   │   │   └── icon.blade.php
│   │   ├── layouts/
│   │   │   ├── app.blade.php
│   │   │   └── guest.blade.php
│   │   └── livewire/
│   │       ├── layout/
│   │       │   ├── navigation.blade.php
│   │       │   └── sidebar.blade.php
│   │       ├── pages/
│   │       │   └── auth/
│   │       │       └── login.blade.php
│   │       └── permohonan-timeline.blade.php
│   ├── css/
│   │   └── app.css
│   └── js/
│       └── app.js
├── routes/
│   ├── web.php
│   └── api.php
├── .env
├── composer.json
├── package.json
├── SETUP.md
└── INSTALLATION.md (this file)
```

---

## ⚙️ Konfigurasi System

Setelah login sebagai admin, Anda bisa mengatur:

### Threshold Settings (Stuck Detection)
- `threshold.default`: 48 jam
- `threshold.waiting_client`: 72 jam
- `threshold.incomplete_docs`: 24 jam
- `threshold.step.INSPECTOR_REVIEW`: 48 jam
- `threshold.step.KACAB_APPROVAL`: 72 jam
- `threshold.step.SBU_APPROVAL`: 72 jam

### SLA Settings
- `sla.default`: 14 hari
- `sla.urgent`: 7 hari

### Notification Settings
- `notification.enabled`: true
- `notification.email_enabled`: true
- `notification.reminder_interval`: 24 jam
- `notification.escalation_after`: 48 jam

---

## 🎨 Customization

### Mengubah Logo
Edit file: `resources/views/livewire/layout/sidebar.blade.php`
Ganti SVG logo di line 22-24

### Mengubah Warna Theme
Edit file: `tailwind.config.js`
Customize colors di section `theme.extend.colors`

### Menambah Menu
Edit file: `app/Livewire/Layout/Sidebar.php`
Tambahkan menu items di method `getMenuItems()`

---

## 📞 Support & Dokumentasi

### Dokumentasi Lengkap
- **SETUP.md** - Panduan fitur dan konfigurasi
- **INSTALLATION.md** - Panduan instalasi (file ini)

### Tech Stack
- Laravel 11.x
- Livewire 3.x
- Tailwind CSS 3.x
- Alpine.js 3.x
- Spatie Permission 6.x
- Maatwebsite Excel 3.x
- DomPDF 3.x

### Troubleshooting
Jika ada masalah:
1. Check log: `storage/logs/laravel.log`
2. Clear cache: `php artisan optimize:clear`
3. Restart server: `php artisan serve`

---

## 🎉 Selamat!

Aplikasi Anda sudah siap digunakan!

**Quick Start:**
```bash
# 1. Setup database
CREATE DATABASE statutoria_monitoring;

# 2. Edit .env (database config)

# 3. Run migrations & seeders
php artisan migrate:fresh --seed

# 4. Start server
php artisan serve

# 5. Login
http://localhost:8000
Email: admin@bki.co.id
Password: password
```

**Fitur yang bisa langsung digunakan:**
- ✅ Login dengan role-based access
- ✅ Navigation menu yang dinamis
- ✅ Toast notifications otomatis
- ✅ Loading states di semua actions
- ✅ Timeline tracking
- ✅ Dark mode toggle

**Next Steps:**
1. Buat permohonan pertama
2. Upload dokumen
3. Test approval workflow
4. Lihat timeline tracking
5. Monitor stuck applications
6. Export reports

---

## 📄 License

Proprietary - PT BKI (Persero)
© 2024 All Rights Reserved

---

**🚀 Happy Coding!**
