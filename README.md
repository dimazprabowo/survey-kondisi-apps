# Client App — Boilerplate Application

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-red?style=for-the-badge&logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/Livewire-4.x-purple?style=for-the-badge&logo=livewire" alt="Livewire">
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-blue?style=for-the-badge&logo=tailwindcss" alt="Tailwind">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php" alt="PHP">
</p>

<p align="center">
  <strong>Laravel Boilerplate Application dengan SSO Integration</strong><br>
  OAuth 2.0 Client • RBAC • User Management • System Configuration
</p>

---

## 📋 Daftar Isi

1. [Tentang Client App](#-tentang-client-app)
2. [Fitur Utama](#-fitur-utama)
3. [Tech Stack](#-tech-stack)
4. [Instalasi](#-instalasi)
5. [Konfigurasi](#-konfigurasi)
6. [IS_USING_SSO Feature](#-is_using_sso-feature)
7. [Database Schema](#-database-schema)
8. [Permissions & Roles](#-permissions--roles)
9. [SSO Integration](#-sso-integration)
10. [Development](#-development)
11. [Production Deployment](#-production-deployment)
12. [Troubleshooting](#-troubleshooting)

---

## 🎯 Tentang Client App

Client App adalah aplikasi Laravel boilerplate yang dapat berfungsi sebagai:

- **OAuth 2.0 Client** — Terintegrasi dengan SSO Server untuk centralized authentication
- **Standalone Application** — Dapat berjalan mandiri tanpa SSO (mode standalone)
- **Boilerplate Template** — Template siap pakai dengan fitur-fitur umum yang dibutuhkan

Aplikasi ini mendukung **dual mode operation**:
- **SSO Mode** (`IS_USING_SSO=true`) — Login via SSO Server + local login
- **Standalone Mode** (`IS_USING_SSO=false`) — Hanya local login, tanpa SSO

---

## ✨ Fitur Utama

### Authentication & Authorization
- ✅ **Dual Login Mode** — SSO login + local login (configurable)
- ✅ Login dengan email & password
- ✅ Register new account
- ✅ Forgot password & reset via email
- ✅ Email verification
- ✅ Google reCAPTCHA v2 protection
- ✅ Session management

### SSO Integration (Optional)
- ✅ OAuth 2.0 Authorization Code Flow
- ✅ Auto-sync user dari SSO Server
- ✅ API endpoints untuk SSO sync
- ✅ Direct DB sync support
- ✅ Conditional SSO UI (show/hide based on config)

### User Management
- ✅ CRUD users dengan Livewire
- ✅ Assign multiple roles per user
- ✅ Toggle active/inactive status
- ✅ Profile management
- ✅ Password change
- ✅ Email change with verification

### Role & Permission Management
- ✅ CRUD roles & permissions
- ✅ Permission-based access control
- ✅ Super admin bypass
- ✅ Dynamic sidebar based on permissions

### Master Data
- ✅ Company management (CRUD)
- ✅ Excel export/import
- ✅ PDF export
- ✅ Soft deletes
- ✅ Route model binding dengan company code

### System Configuration
- ✅ Key-value configuration management
- ✅ UI-based config editor
- ✅ Runtime config override
- ✅ App name, timezone, etc.

### Communication Features
- ✅ Real-time chat (Laravel Reverb)
- ✅ Notification system
- ✅ WebSocket support

### UI/UX
- ✅ Dark mode support
- ✅ Responsive design
- ✅ Custom error pages (400-504)
- ✅ Loading states & animations
- ✅ Toast notifications

---

## 🛠 Tech Stack

| Komponen | Teknologi | Versi |
|----------|-----------|-------|
| Framework | Laravel | 12.x |
| PHP | PHP | 8.2+ |
| UI Framework | Livewire | 4.x |
| RBAC | Spatie Permission | 6.x |
| Database | PostgreSQL / MySQL / SQLite | 17 / 8 / - |
| CSS | TailwindCSS | 3.x |
| JS | Alpine.js | 3.x |
| WebSocket | Laravel Reverb | 1.x |
| PDF | DomPDF | 3.x |
| Excel | Maatwebsite Excel | 3.x |
| Image | Intervention Image | 3.x |
| Activity Log | Spatie Activity Log | 4.x |
| Security | reCAPTCHA v2 | - |

---

## 📦 Instalasi

### Prasyarat

- PHP 8.2 atau lebih tinggi
- Composer 2.x
- Node.js 18+ & npm
- PostgreSQL 17 atau MySQL 8 atau SQLite
- Git

### Langkah Instalasi

```bash
# 1. Clone repository (jika dari git)
git clone <repository-url>
cd client-app

# 2. Install dependencies
composer install
npm install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Konfigurasi database di .env
# Edit .env sesuai kebutuhan (lihat section Konfigurasi)

# 5. Migrate & seed database
php artisan migrate:fresh --seed

# 6. Build assets
npm run build

# 7. Jalankan server (buka terminal terpisah untuk masing-masing)
# Terminal 1: Laravel server
php artisan serve --port=8999

# Terminal 2: Queue worker (untuk background jobs)
php artisan queue:work

# Terminal 3: Reverb WebSocket server (untuk real-time chat)
php artisan reverb:start
```

> **Catatan:** 
> - Jika menggunakan SSO, pastikan SSO Server sudah running dan kredensial OAuth sudah dikonfigurasi di `.env`.
> - Queue worker diperlukan untuk SSO sync dan background jobs.
> - Reverb server diperlukan untuk fitur real-time chat dan notifications.

---

## ⚙️ Konfigurasi

### Environment Variables (.env)

```env
# Application
APP_NAME="MANTRA"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8999

# SSO Configuration
# Set to true untuk enable SSO, false untuk standalone
IS_USING_SSO=true

# OAuth Credentials (dari SSO Server seeder output)
SSO_CLIENT_ID=your-client-id
SSO_CLIENT_SECRET=your-client-secret
SSO_BASE_URL=http://localhost:8111
SSO_REDIRECT_URI=http://localhost:8999/auth/callback

# API Secret (untuk SSO sync via API)
SSO_API_SECRET=client-sso-secret-key-2026

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=client_marine
DB_USERNAME=postgres
DB_PASSWORD=root

# Database (MySQL - Production)
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=client_app
# DB_USERNAME=root
# DB_PASSWORD=

# Google reCAPTCHA v2 (Opsional)
RECAPTCHA_SITE_KEY=your-site-key
RECAPTCHA_SECRET_KEY=your-secret-key

# Mail (untuk reset password & email verification)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@app.company.com"
MAIL_FROM_NAME="${APP_NAME}"

# Laravel Reverb (WebSocket)
REVERB_APP_ID=1001
REVERB_APP_KEY=laravel-reverb-key
REVERB_APP_SECRET=laravel-reverb-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

### Port Default

- **Development:** `8999`
- **Reverb WebSocket:** `8080`
- **Production:** Sesuaikan dengan web server

---

## 🔀 IS_USING_SSO Feature

### Overview

Client App dapat berjalan dalam 2 mode berbeda:

### Mode 1: SSO Enabled (`IS_USING_SSO=true`)

**Konfigurasi:**
```env
IS_USING_SSO=true
SSO_CLIENT_ID=your-client-id
SSO_CLIENT_SECRET=your-client-secret
SSO_BASE_URL=http://localhost:8111
SSO_REDIRECT_URI=http://localhost:8999/auth/callback
SSO_API_SECRET=your-api-secret
```

**Fitur Aktif:**
- ✅ Tombol "Login dengan SSO Server" muncul di halaman login
- ✅ User dapat login via SSO atau local
- ✅ OAuth routes terdaftar (`/auth/sso/redirect`, `/auth/callback`)
- ✅ SSO API sync endpoints aktif (`/api/sso/*`)
- ✅ Auto-sync user dari SSO Server
- ✅ Roles & permissions sync dari SSO

**Use Case:**
- Aplikasi production yang terintegrasi dengan SSO Server
- Multi-app environment dengan centralized auth
- Enterprise deployment

### Mode 2: Standalone (`IS_USING_SSO=false`)

**Konfigurasi:**
```env
IS_USING_SSO=false
# SSO credentials tidak diperlukan
```

**Fitur Aktif:**
- ✅ Hanya local login tersedia
- ✅ Register, forgot password, email verification
- ✅ User management mandiri
- ✅ Roles & permissions management mandiri
- ❌ Tombol SSO tersembunyi
- ❌ OAuth routes TIDAK terdaftar (404)
- ❌ SSO API endpoints TIDAK terdaftar (404)

**Use Case:**
- Development/testing tanpa SSO Server
- Standalone deployment
- Demo/prototype
- Small business yang tidak butuh SSO

### Switching Modes

```bash
# 1. Edit .env
IS_USING_SSO=false  # atau true

# 2. Clear caches
php artisan config:clear
php artisan route:clear

# 3. Restart server
php artisan serve --port=8999
```

**⚠️ Catatan Penting:**
- Direct DB sync dari SSO Server masih bisa bekerja meskipun `IS_USING_SSO=false` karena beroperasi di level database
- Untuk benar-benar standalone, jangan konfigurasi DB connection di SSO Server

---

## 🗄 Database Schema

### Tables Utama

| Table | Deskripsi | Kolom Penting |
|-------|-----------|---------------|
| `users` | User accounts | id, name, email, password, company_id, is_active |
| `companies` | Master data perusahaan | id, code, name, email, phone, status |
| `system_configurations` | Key-value config | key, value, type |
| `chats` | Chat rooms | id, name, type |
| `chat_messages` | Chat messages | id, chat_id, user_id, message |
| `chat_participants` | Chat members | chat_id, user_id, last_read_at |
| `notifications` | User notifications | id, user_id, title, message, read_at |
| `roles` | Spatie roles | id, name, guard_name |
| `permissions` | Spatie permissions | id, name, guard_name |
| `sessions` | Session management | id, user_id, ip_address, user_agent |
| `cache` | Cache store | key, value, expiration |
| `jobs` | Queue jobs | id, queue, payload |

### Migrations

Total: **18+ migrations**

Termasuk:
- Laravel default tables (users, sessions, cache, jobs)
- Spatie Permission tables
- Custom tables (companies, chats, notifications, system_configurations)

---

## 🔐 Permissions & Roles

### Permissions (Client App)

| Permission | Deskripsi |
|------------|----------|
| `dashboard_view` | Akses dashboard |
| `users_view` | Lihat daftar user |
| `users_create` | Tambah user |
| `users_update` | Edit user |
| `users_delete` | Hapus user |
| `roles_view` | Lihat roles |
| `roles_create` | Tambah role |
| `roles_update` | Edit role |
| `roles_delete` | Hapus role |
| `companies_view` | Lihat companies |
| `companies_create` | Tambah company |
| `companies_update` | Edit company |
| `companies_delete` | Hapus company |
| `configuration_view` | Lihat system config |
| `configuration_update` | Edit system config |
| `notifications_view` | Lihat notifications |
| `notifications_send` | Kirim notifications |
| `chat_view` | Akses chat |

### Roles Default

| Role | Permissions | Deskripsi |
|------|-------------|----------|
| `super admin` | ALL | Full access |
| `admin` | Most permissions | Admin biasa |
| `user` | Limited permissions | User biasa |

### Test Accounts (Setelah Seed)

| Email | Password | Role | Keterangan |
|-------|----------|------|------------|
| superadmin@app.com | password | super admin | Full access |
| admin@app.com | password | admin | Admin |
| user@app.com | password | user | User biasa |

---

## 🔗 SSO Integration

### OAuth Flow (ketika IS_USING_SSO=true)

1. User klik "Login dengan SSO Server"
2. Redirect ke SSO Server `/oauth/authorize`
3. User login di SSO Server
4. SSO redirect kembali ke `/auth/callback` dengan authorization code
5. Client app exchange code untuk access token
6. Client app fetch user data dari SSO `/api/user`
7. Client app create/update local user
8. User auto-login di client app

### SSO Sync Endpoints (API)

Ketika SSO Server menggunakan API sync method:

| Method | Endpoint | Auth | Deskripsi |
|--------|----------|------|----------|
| GET | `/api/sso/ping` | X-SSO-Secret | Health check |
| POST | `/api/sso/users/sync` | X-SSO-Secret | Sync/update user |
| POST | `/api/sso/users/remove` | X-SSO-Secret | Nonaktifkan user |
| POST | `/api/sso/users/sync-roles` | X-SSO-Secret | Sync user roles |
| GET | `/api/sso/users` | X-SSO-Secret | List all users |
| POST | `/api/sso/roles/sync` | X-SSO-Secret | Sync role |
| POST | `/api/sso/roles/delete` | X-SSO-Secret | Delete role |
| GET | `/api/sso/roles` | X-SSO-Secret | List all roles |
| GET | `/api/sso/permissions` | X-SSO-Secret | List all permissions |

**Authentication:** Header `X-SSO-Secret: your-api-secret`

---

## 💻 Development

### Running Development Server

```bash
# Terminal 1: Laravel server
php artisan serve --port=8999

# Terminal 2: Vite dev server
npm run dev

# Terminal 3: Queue worker
php artisan queue:listen

# Terminal 4: Reverb WebSocket server
php artisan reverb:start --debug

# Terminal 5: Logs (opsional)
php artisan pail
```

### Atau gunakan composer script:

```bash
composer dev
# Menjalankan server, queue, logs, vite, dan reverb secara bersamaan
```

### Useful Commands

```bash
# Clear all caches
php artisan optimize:clear

# List all routes
php artisan route:list

# List all permissions
php artisan permission:show

# Reset database
php artisan migrate:fresh --seed

# Test SSO connection
php artisan tinker
>>> config('services.sso.enabled')
>>> config('services.sso.client_id')
```

---

## 🚀 Production Deployment

### Pre-Deployment Checklist

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Use HTTPS for `APP_URL`
- [ ] Configure production database (MySQL recommended)
- [ ] Set `IS_USING_SSO=true` (jika menggunakan SSO)
- [ ] Configure SSO credentials (jika menggunakan SSO)
- [ ] Set strong `APP_KEY`
- [ ] Configure mail server (SMTP)
- [ ] Enable reCAPTCHA
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Run `npm run build`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Setup queue worker (Supervisor)
- [ ] Setup Reverb server (if using chat)
- [ ] Setup SSL certificate
- [ ] Configure firewall

### Environment Variables (Production)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.company.com

# SSO (jika digunakan)
IS_USING_SSO=true
SSO_CLIENT_ID=production-client-id
SSO_CLIENT_SECRET=production-client-secret
SSO_BASE_URL=https://sso.company.com
SSO_REDIRECT_URI=https://app.company.com/auth/callback
SSO_API_SECRET=production-api-secret

DB_CONNECTION=mysql
DB_HOST=your-mysql-host
DB_PORT=3306
DB_DATABASE=client_app
DB_USERNAME=your-user
DB_PASSWORD=your-secure-password

RECAPTCHA_SITE_KEY=production-site-key
RECAPTCHA_SECRET_KEY=production-secret-key

MAIL_MAILER=smtp
MAIL_HOST=smtp.company.com
MAIL_PORT=587
MAIL_USERNAME=noreply@company.com
MAIL_PASSWORD=secure-password
MAIL_ENCRYPTION=tls
```

---

## 🔧 Troubleshooting

### SSO login tidak muncul

**Penyebab:** `IS_USING_SSO=false` atau config cache

**Solusi:**
```bash
IS_USING_SSO=true  # di .env
php artisan config:clear
php artisan route:clear
```

### OAuth error: invalid_client

**Penyebab:** Client ID atau Secret salah

**Solusi:**
1. Cek output seeder SSO Server
2. Salin ulang credentials ke `.env`
3. `php artisan config:clear`
4. Restart server

### 404 pada route SSO

**Penyebab:** `IS_USING_SSO=false`

**Solusi:** Set `IS_USING_SSO=true` dan clear caches

### User tidak ter-sync dari SSO

**Penyebab:** API secret tidak cocok atau endpoint tidak accessible

**Solusi:**
1. Verifikasi `SSO_API_SECRET` sama dengan SSO Server
2. Test endpoint: `curl http://localhost:8999/api/sso/ping -H "X-SSO-Secret: your-secret"`
3. Cek firewall/network

### Reverb WebSocket error

**Penyebab:** Reverb server tidak running

**Solusi:**
```bash
php artisan reverb:start --debug
```

---

## 📚 Dokumentasi Terkait

- [Portal SSO README](../README.md) - Dokumentasi utama proyek
- [SSO Server README](../sso-server/README.md) - Dokumentasi SSO Server
- [Implementation Summary](../IMPLEMENTATION_SUMMARY.md) - Technical documentation
- [Panduan Implementasi](../PANDUAN_IMPLEMENTASI.md) - Panduan lengkap

---

## 📄 Lisensi

MIT License

---

## 🤝 Kontributor

Developed by PT. Biro Klasifikasi Indonesia Development Team

---

**Built with:** [Laravel](https://laravel.com) • [Livewire](https://livewire.laravel.com) • [Spatie Permission](https://spatie.be/docs/laravel-permission) • [TailwindCSS](https://tailwindcss.com) • [Alpine.js](https://alpinejs.dev) • [Laravel Reverb](https://reverb.laravel.com)
