# Tint Data Upload Application

<p align="center">
  <strong>A professional-grade Laravel application for managing and processing tint colorant data with intelligent Excel file handling and database indexing.</strong>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.9-FF2D20?logo=laravel" alt="Laravel Version">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php" alt="PHP Version">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="License">
  <img src="https://img.shields.io/badge/Docker-Ready-2496ED?logo=docker" alt="Docker Ready">
</p>

---

## 📋 Table of Contents

-   [Overview](#overview)
-   [Key Features](#key-features)
-   [Technology Stack](#technology-stack)
-   [System Requirements](#system-requirements)
-   [Quick Start](#quick-start)
-   [Project Structure](#project-structure)
-   [Core Features & Usage](#core-features--usage)
-   [API Routes](#api-routes)
-   [Database](#database)
-   [Configuration](#configuration)
-   [Development](#development)
-   [Testing](#testing)
-   [Troubleshooting](#troubleshooting)
-   [Contributing](#contributing)
-   [License](#license)

---

## 🎯 Overview

The **Tint Data Upload Application** is a sophisticated data management platform designed for paint and tint industry professionals. It provides seamless Excel file upload, processing, and database management capabilities with advanced colorant mapping, indexing, and data transformation features.

This application automates the complex workflows of handling tint formulations, colorant codes, and product information, making it ideal for manufacturers, distributors, and color laboratories.

---

## ✨ Key Features

### 📊 Excel Data Processing

-   **Multi-format support**: Handle multiple Excel sheet formats simultaneously
-   **Smart colorant mapping**: Automatic mapping of colorant codes to quantities
-   **Flexible indexing**: Multiple database indexing strategies for different data structures
-   **Data validation**: Built-in validation for Excel imports

### 🗂️ Data Management

-   **Dual indexing systems**: Optimized for standard and non-standard data formats
-   **RGB color support**: Process with and without RGB color data
-   **Batch processing**: Efficient handling of large datasets
-   **Data transformation**: Convert raw Excel data into structured database records

### 🐳 DevOps Ready

-   **Docker containerization**: Complete Docker Compose setup
-   **MySQL database**: Persistent data storage with automatic migrations
-   **phpMyAdmin**: Visual database management interface
-   **Nginx server**: Production-ready web server

### 🧪 Quality Assurance

-   **PHPUnit testing**: Comprehensive test suite
-   **Laravel Telescope**: Advanced debugging and monitoring
-   **Database traceability**: Full audit trails for data operations

---

## 🛠️ Technology Stack

| Layer                 | Technology                                            |
| --------------------- | ----------------------------------------------------- |
| **Backend Framework** | Laravel 11.9                                          |
| **Language**          | PHP 8.2+                                              |
| **Database**          | MySQL 5.7+                                            |
| **Web Server**        | Nginx                                                 |
| **Frontend Build**    | Vite 5.0                                              |
| **Excel Processing**  | PhpSpreadsheet, Maatwebsite/Excel, Spatie SimpleExcel |
| **Container**         | Docker & Docker Compose                               |
| **Testing**           | PHPUnit 11                                            |
| **Code Quality**      | Laravel Pint                                          |

---

## 💻 System Requirements

### Minimum Requirements

-   **PHP**: 8.2 or higher
-   **MySQL**: 5.7 or higher
-   **Node.js**: 16+ (for frontend builds)
-   **Composer**: Latest version

### For Docker Deployment

-   **Docker Desktop**: Latest version
-   **RAM**: Minimum 4GB allocated to Docker (8GB recommended)
-   **Available Ports**: 80, 3306, 8081
-   **Disk Space**: 5GB free space

### Supported Operating Systems

-   macOS (Intel & Apple Silicon M1/M2)
-   Linux (Ubuntu, Debian, CentOS)
-   Windows (via WSL 2)

---

## 🚀 Quick Start

### Option 1: Docker Deployment (Recommended)

#### Prerequisites

```bash
# Ensure Docker Desktop is running
docker --version
docker-compose --version
```

#### Step 1: Clone the Repository

```bash
git clone <repository-url> tint-data-upload
cd tint-data-upload
```

#### Step 2: Configure Environment

```bash
# Copy environment template
cp .env.example .env

# Edit configuration if needed (optional)
# The container will auto-generate APP_KEY on first run
nano .env
```

#### Step 3: Start the Application

```bash
# Build and start all services
docker-compose up -d --build

# Check service status
docker-compose ps
```

#### Step 4: Run Migrations

```bash
# Migrations run automatically on first boot
# If needed manually:
docker-compose exec painter_app php artisan migrate
```

#### Step 5: Access the Application

-   **Application**: http://localhost
-   **phpMyAdmin**: http://localhost:8081
-   **MySQL**: `localhost:3306`

For detailed Docker instructions, see [DOCKER.md](DOCKER.md).

---

### Option 2: Local Development Setup

#### Step 1: Clone and Install

```bash
git clone <repository-url> tint-data-upload
cd tint-data-upload

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

#### Step 2: Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

#### Step 3: Database Setup

```bash
# Create database (update .env with your DB credentials first)
php artisan migrate
```

#### Step 4: Run Development Server

```bash
# Terminal 1: Start PHP server
php artisan serve

# Terminal 2: Start Vite development server
npm run dev
```

Access the application at `http://localhost:8000`

---

## 📁 Project Structure

```
tint-data-upload-application/
├── app/
│   ├── Http/
│   │   └── Controllers/          # Request handlers
│   │       ├── ExcelController.php
│   │       ├── ForDifferentController.php
│   │       ├── OldStyleController.php
│   │       └── WithoutRBGController.php
│   ├── Models/
│   │   └── User.php              # User model
│   ├── Traits/                   # Reusable functionality
│   │   ├── ColorantMapper.php    # Colorant code mapping
│   │   ├── ExcelTrait.php        # Excel processing
│   │   ├── DatabaseTrait.php     # Database operations
│   │   └── DatabaseTraitIndexing.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── TelescopeServiceProvider.php
├── config/                       # Application configuration
│   ├── app.php
│   ├── database.php
│   ├── excel.php
│   └── ...
├── database/
│   ├── migrations/               # Database schema
│   ├── factories/
│   └── seeders/
├── public/                       # Web root
│   └── storage/
├── resources/
│   ├── views/                    # Blade templates
│   ├── css/
│   └── js/
├── routes/
│   ├── web.php                   # Web routes
│   └── console.php
├── storage/                      # File storage
├── tests/                        # Test suite
├── docker-compose.yml            # Docker configuration
├── Dockerfile
├── nginx.conf
└── package.json
```

---

## 🎯 Core Features & Usage

### 1. Excel Data Upload and Processing

#### Standard Excel Processing

**Route**: `POST /excel`

Upload an Excel file with the following structure:

-   **Sheet: "Colorant"** - Contains colorant definitions (Column C: colorant codes)
-   **Sheet: "Combined Data"** - Contains product data and quantities

```bash
curl -X POST http://localhost/excel \
  -F "file=@sample-data.xlsx"
```

**Features**:

-   Automatic colorant code extraction
-   Quantity mapping across products
-   Data validation and error reporting
-   Processed data export

#### Multiple Format Support

The application supports three distinct Excel processing modes:

| Controller               | Purpose                              | Best For            |
| ------------------------ | ------------------------------------ | ------------------- |
| `ExcelController`        | Standard format with colorant sheets | Normal data imports |
| `ForDifferentController` | Alternative sheet structure          | Legacy systems      |
| `OldStyleController`     | Old format compatibility             | Historical data     |
| `WithoutRBGController`   | RGB-free processing                  | Non-color data      |

### 2. Colorant Mapping Engine

**File**: [app/Traits/ColorantMapper.php](app/Traits/ColorantMapper.php)

The ColorantMapper trait handles:

-   Extraction of unique colorant codes
-   Cross-sheet data correlation
-   Colorant quantity calculations
-   Header transformation and mapping

**Example Processing Flow**:

```
Input Excel
    ↓
Extract Colorant Codes from "Colorant" sheet
    ↓
Map Quantities from "Combined Data" sheet
    ↓
Transform Headers (replace indices with color names)
    ↓
Validate and Process Data
    ↓
Export Processed Records
```

### 3. Database Indexing Strategies

#### Standard Indexing

**File**: [app/Traits/DatabaseTraitIndexing.php](app/Traits/DatabaseTraitIndexing.php)

Optimized for conventional data structures with proper headers and formatting.

#### Alternative Indexing

**File**: [app/Traits/DatabaseTraitIndexingForDifferent.php](app/Traits/DatabaseTraitIndexingForDifferent.php)

Handles non-standard formats, merged cells, and irregular structures.

### 4. Data Export

**Route**: `POST /download`

Export processed data in multiple formats:

-   Excel (.xlsx)
-   CSV (.csv)
-   PDF (if configured)

```bash
curl -X POST http://localhost/download \
  -H "Content-Type: application/json" \
  -d '{"format": "excel"}'
```

---

## 🔌 API Routes

| Method | Route             | Controller             | Action   | Description             |
| ------ | ----------------- | ---------------------- | -------- | ----------------------- |
| GET    | `/`               | ExcelController        | index    | Homepage / Upload page  |
| POST   | `/excel`          | ExcelController        | store    | Process Excel upload    |
| GET    | `/excel/{id}`     | ExcelController        | show     | View processed result   |
| POST   | `/download`       | ExcelController        | download | Download processed file |
| GET    | `/success`        | -                      | -        | Success page            |
| POST   | `/differentExcel` | ForDifferentController | store    | Process alt format      |
| GET    | `/differentExcel` | ForDifferentController | index    | Alt format upload       |
| POST   | `/oldstyleExcel`  | OldStyleController     | store    | Process legacy format   |
| GET    | `/oldstyleExcel`  | OldStyleController     | index    | Legacy format upload    |
| POST   | `/withoutRBG`     | WithoutRBGController   | store    | Process non-RGB data    |

---

## 🗄️ Database

### Migrations

The application includes automatic database setup:

```bash
# View migrations
php artisan migrate:status

# Rollback migrations (dev only)
php artisan migrate:rollback

# Fresh migration
php artisan migrate:fresh
```

### Included Tables

-   `users` - User accounts
-   `cache` - Cache storage
-   `jobs` - Queue jobs
-   `telescope_entries` - Debug information (if enabled)

### Database Access

**Via Docker**:

```bash
docker-compose exec painter_mysql mysql -u root -p[password]
```

**phpMyAdmin**: http://localhost:8081

---

## ⚙️ Configuration

### Environment Variables

Create/edit `.env` file:

```env
# Application
APP_NAME="Tint Data Upload"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tint_app
DB_USERNAME=root
DB_PASSWORD=password

# Excel Processing
EXCEL_EXPORT_CHUNK_SIZE=1000
EXCEL_IMPORT_MEMORY_LIMIT=256M

# File Storage
FILESYSTEM_DISK=local
TEMP_UPLOAD_PATH=uploads/temp
```

### Key Configuration Files

| File                     | Purpose                   |
| ------------------------ | ------------------------- |
| `config/app.php`         | Application settings      |
| `config/database.php`    | Database configuration    |
| `config/excel.php`       | Excel processing options  |
| `config/filesystems.php` | File storage setup        |
| `config/telescope.php`   | Debug tools configuration |

---

## 🔧 Development

### Code Standards

The project uses **Laravel Pint** for code formatting:

```bash
# Check code style
php artisan pint --test

# Fix code style issues
php artisan pint
```

### Project Commands

```bash
# Artisan commands
php artisan list                    # Show all commands
php artisan tinker                  # Interactive shell
php artisan cache:clear             # Clear cache
php artisan config:cache            # Cache configuration

# Database
php artisan migrate                 # Run migrations
php artisan migrate:refresh         # Fresh migration
php artisan db:seed                 # Seed database

# Development
npm run dev                         # Watch mode
npm run build                       # Production build
php artisan serve                   # Start dev server
```

### Debugging with Telescope

Laravel Telescope provides advanced debugging:

```bash
# Enable Telescope (if disabled)
php artisan telescope:install

# Access Telescope
# Navigate to: http://localhost:8000/telescope
```

---

## 🧪 Testing

### Run Tests

```bash
# All tests
php artisan test

# Specific test
php artisan test tests/Feature/ExcelControllerTest.php

# With coverage
php artisan test --coverage

# Stop on failure
php artisan test --stop-on-failure
```

### Test Structure

```
tests/
├── Feature/              # Integration tests
└── Unit/                 # Unit tests
```

---

## 🚨 Troubleshooting

### Common Issues

#### 1. **Port Already in Use**

```bash
# Find service using port 80
lsof -i :80

# Kill process or use different port
sudo kill -9 <PID>
```

#### 2. **Database Connection Error**

```bash
# Check Docker services
docker-compose ps

# Restart database
docker-compose restart painter_mysql

# Verify credentials in .env
```

#### 3. **Memory Exhausted During Excel Processing**

```env
# Increase memory limit in .env
EXCEL_IMPORT_MEMORY_LIMIT=512M

# Or temporarily in code:
ini_set('memory_limit', '512M');
```

#### 4. **File Upload Permissions**

```bash
# Fix storage permissions
chmod -R 775 storage/
chmod -R 775 bootstrap/cache/
```

#### 5. **Excel File Not Found**

-   Ensure file is in correct location: `/storage/app/uploads/`
-   Verify file permissions: `ls -la storage/app/uploads/`
-   Check file format is `.xlsx`

### Docker Logs

```bash
# Application logs
docker-compose logs painter_app

# Nginx logs
docker-compose logs painter_nginx

# MySQL logs
docker-compose logs painter_mysql

# Real-time logs
docker-compose logs -f painter_app
```

---

## 📚 Additional Resources

-   [Laravel Documentation](https://laravel.com/docs)
-   [PhpSpreadsheet Documentation](https://phpspreadsheet.readthedocs.io/)
-   [Docker Documentation](https://docs.docker.com/)
-   [MySQL Documentation](https://dev.mysql.com/doc/)

### Project Documentation

-   [Docker Setup Guide](DOCKER.md)
-   [API Documentation](docs/api.md) _(if available)_
-   [Contributing Guidelines](CONTRIBUTING.md) _(if available)_

---

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:

-   Code follows Laravel best practices
-   PHPUnit tests pass
-   Code is formatted with Pint
-   Commit messages are descriptive

---

## 📄 License

This project is licensed under the **MIT License** - see the LICENSE file for details.

---

## 👥 Support

For issues, feature requests, or questions:

-   Open an issue on the repository
-   Contact the development team
-   Check existing documentation

---

<p align="center">
  <strong>Built with ❤️ using Laravel, Nginx, and MySQL</strong><br>
  <em>Making tint data management simple and efficient</em>
</p>
