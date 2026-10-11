# UNB Wire

> **News Agency Wire Service & Real-Time Editorial Distribution Platform**  
> Built with Laravel 12, Livewire 3, Alpine.js, Tailwind CSS, PostgreSQL, Redis, and Reverb.

---

## 🛠 Tech Stack

- **Backend:** Laravel 12 (PHP ^8.2), MVC + Service + Repository architecture
- **Admin & Editorial UI:** Blade + Livewire 3 + Alpine.js + Tailwind CSS
- **Asset Bundler:** Vite
- **Database:** PostgreSQL 16 (SQLite supported for testing/local CI)
- **Queues & Cache:** Redis + Laravel Horizon
- **Real-time WebSockets:** Laravel Reverb
- **Search Engine:** Meilisearch

---

## 🚀 Getting Started

### 1. Prerequisites

Make sure the following are installed on your machine:
- **PHP** >= 8.2 (with `pdo`, `mbstring`, `intl`, `xml`, `curl` extensions)
- **Composer**
- **Node.js** (v18+) & **npm**
- **PostgreSQL** (or SQLite for lightweight local dev)
- **Redis** (optional for basic dev, required for Horizon/queues in production)

---

### 2. Initial Setup

```bash
# 1. Clone repository and navigate into project directory
cd unb-wire-ngx

# 2. Install dependencies
composer install
npm install

# 3. Environment configuration
cp .env.example .env     # On Windows CMD/PowerShell: copy .env.example .env
php artisan key:generate

# 4. Database migration and seeding
php artisan migrate --seed

# 5. Build assets
npm run build
```

---

## 💻 Running Development Servers

To run the full stack locally, you need the **Laravel Web Server**, **Vite (frontend compiler)**, and **Queue Worker** (for media processing & fan-out delivery).

### Option A: Windows PowerShell (Multi-Window Launcher)

Run this one-liner in Windows PowerShell to clear caches and launch each process in its own terminal window:

```powershell
php artisan optimize:clear; Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan serve"; Start-Process powershell -ArgumentList "-NoExit", "-Command", "npm run dev"; Start-Process powershell -ArgumentList "-NoExit", "-Command", "php artisan queue:listen"
```
*(The `-NoExit` flag ensures terminal windows stay open so you can view live error logs and output).*

---

### Option B: Linux / macOS (Bash / Zsh)

#### 1. Single Terminal (Concurrent Runner — Recommended):
```bash
php artisan optimize:clear && composer run dev
```

#### 2. Background Processes:
```bash
php artisan optimize:clear && php artisan serve & npm run dev & php artisan queue:listen
```

---

### Option C: Manual (Separate Terminals)

If you prefer launching each service individually in separate tabs/terminals:

| Service | Command | URL / Note |
|---|---|---|
| **Laravel Server** | `php artisan serve` | [http://localhost:8000](http://localhost:8000) |
| **Vite Dev Server** | `npm run dev` | Hot Module Replacement (HMR) |
| **Queue Worker** | `php artisan queue:listen` | Processes photo ingestion & jobs |
| **Realtime WebSockets** | `php artisan reverb:start` | Realtime push notifications |

---

## 🧹 Maintenance & Cache Clearing

When experiencing template desync, stale config, or styling cache issues:

```bash
php artisan optimize:clear
```
*(Clears compiled config, application cache, routes, views, and blade icons).*

---

## 🧪 Testing & Quality Gates

```bash
# Run unit & feature test suite
php artisan test

# Run database schema parity check
php scripts/schema-parity-check.php

# Run end-to-end Playwright tests (ensure app is running on localhost:8000)
npx playwright test
```

---

## 📚 Documentation & Architecture

- **UI Prototypes & Contract:** [`app-data/README.md`](app-data/README.md)
- **Functional Requirements (FRD):** [`app-data/v1-functional-requirements.md`](app-data/v1-functional-requirements.md)
- **Database Architecture:** [`app-data/v1-database-design.md`](app-data/v1-database-design.md)
- **Architecture Decisions (ADR):** [`docs/knowledge-inventory/decisions.md`](docs/knowledge-inventory/decisions.md)
- **Agent Guidelines & Quality Gates:** [`AGENTS.md`](AGENTS.md)
