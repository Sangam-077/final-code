
# Ravenhill Coffee Shop Management System

Welcome to the Ravenhill Coffee Shop Management System, a custom Point of Sale (POS) and management solution designed for Ravenhill Coffee, a specialty coffee roaster and retailer based in Melbourne. This project aims to streamline daily sales transactions, manage inventory, and provide reporting features for the new coffee shop opening in Melbourne CBD in January 2023.

## Overview

- Client: Ravenhill Coffee, founded in 2009 by Fleur Studd and Jason Scheltus, dedicated to sustainable and high-quality coffee sourcing and roasting.
- Purpose: Automate and enhance the efficiency of sales, user management, and reporting for the coffee shop.
- Key Features: Secure login with role-based access (Admin, Staff, Cashier), product catalog management, order processing, inventory tracking, and daily sales reports.
- Last Updated: 10:49 AM AEST, Saturday, September 27, 2025

## Project Structure

The project is organized as follows:


C:\xampp\htdocs\project\
├── Images\
│   ├── aakriti.jpg
│   ├── about.jpg
│   ├── background.jpg
│   ├── background1.jpg
│   ├── bg.jpg
│   ├── binary.jpg
│   ├── bishal.jpg
│   ├── cap.jpg
│   ├── coffee-bg.jpg
│   ├── cold-brew.jpg
│   ├── cross.jpg
│   ├── egg-on-toast.jpg
│   ├── es.jpg
│   ├── espresso.jpg
│   ├── flat-white.jpg
│   ├── granola.jpg
│   ├── iced-coffee.jpg
│   ├── iced-latte.jpg
│   ├── interior.jpg
│   ├── landing.jpg
│   ├── latte.jpg
│   ├── level1.jpg
│   ├── logo.jpg
│   ├── long-black.jpg
│   ├── matcha-latte.jpg
│   ├── matcha.jpg
│   ├── mocha.jpg
│   ├── sangam.jpg
│   ├── ube-ice-matcha.jpg
│   └── vishal.jpg
├── pos\
│   ├── config.php          # Configuration settings for POS
│   ├── index.php           # Main POS interface
│   ├── inventory.php       # Inventory management
│   ├── logout_cashiers.php # Cashier logout functionality
│   ├── manage_cashiers.php # Manage cashier accounts
│   ├── process_order.php   # Process customer orders
│   ├── reports.php         # Generate sales reports
│   ├── script.js           # JavaScript for POS interactivity
│   └── style.css           # CSS for POS styling
├── vendor\
│   ├── autoload.php        # Composer autoloader
│   ├── bin\                # Composer binary files
│   ├── composer\           # Composer library files
│   ├── firebase\           # Firebase-related dependencies
│   ├── google\             # Google API dependencies
│   ├── guzzlehttp\         # HTTP client library
│   ├── monolog\            # Logging library
│   ├── paragonie\          # Security libraries
│   ├── phpseclib\          # PHP secure communications library
│   ├── psr\                # PHP Standard Recommendations
│   ├── ralouphie\          # URL utilities
│   ├── setasign\           # PDF generation library
│   └── symfony\            # Symfony components
├── about_to_cart.php       # Transition from about to cart
├── add_user.php            # Add new user functionality
├── admin_dashboard.php     # Admin interface
├── book_table.php          # Table reservation system
├── cart_page.php           # Shopping cart display
├── checkout.php            # Checkout process
├── coffee-bg.jpg           # Background image
├── composer.json           # Composer dependency file
├── composer.lock           # Locked Composer dependencies
├── Composer-Setup.exe      # Composer installer
├── contact.php             # Contact page
├── db_connect.php          # Database connection script
├── edit_product.php        # Edit product details
├── edit_user.php           # Edit user profiles
├── fetch_order_details.php # Fetch order details
├── generate_report.php     # Report generation script
├── hash.php                # Password hashing utility
├── hash_test.php           # Test password hashing
├── header.php              # Common header include
├── login.php               # User login page
├── logout.php              # User logout functionality
├── menu.php                # Menu display
├── orders.php              # Order management
├── password.php            # Password management
├── php.errors.txt          # Error log file
├── profile.php             # User profile page
├── ravenhillfinal.sql      # Database schema
├── register.php            # User registration
├── request_reset.php       # Password reset request
├── reset_password_handler.php # Password reset handler
├── script.js               # General JavaScript file
├── social_login.php        # Social login integration
├── staff.php               # Staff management
├── style.css               # General CSS styling
└── test.php                # Test script


## Prerequisites

- Software:
  - XAMPP with Apache, MySQL, and PHP
  - Composer for dependency management
- Hardware:
  - Server: Intel Core i5, 16GB RAM, 500GB SSD
  - POS Terminals: Touchscreen PCs with Windows 10/11, barcode scanners
  - Network: High-speed Wi-Fi/router
- Database: MySQL with the `ravenhillfinal` database imported from `ravenhillfinal.sql`

## Installation

1. Clone or Copy the Repository:
   - Place the project folder in `C:\xampp\htdocs\project\` or your web server directory.

2. Set Up the Database:
   - Import `ravenhillfinal.sql` into your MySQL database using phpMyAdmin or the MySQL command line.
   - Update `db_connect.php` with your MySQL credentials (host, username, password, database name).

3. Install Dependencies:
   - Run `Composer-Setup.exe` if Composer is not installed.
   - Navigate to the project root and run `composer install` to install vendor dependencies.

4. Configure the Environment:
   - Edit `pos/config.php` with any specific settings (e.g., database credentials if separate from `db_connect.php`).
   - Ensure the `Images` folder and its contents are accessible.

5. Start the Server:
   - Start Apache and MySQL in XAMPP.
   - Access the project at `http://localhost/project/` or the appropriate URL.

## Usage

- Login: Access `login.php` to log in as Admin, Staff, or Cashier using valid credentials.
- POS System: Use `pos/index.php` for point-of-sale operations, including order processing and inventory management.
- Admin Functions: Use `admin_dashboard.php` to manage users, products, and reports.
- Customer Features: Explore `menu.php`, `cart_page.php`, and `checkout.php` for ordering.
- Reports: Generate daily sales reports via `reports.php`.



## Out-of-Scope Features

Certain features were attempted but could not be fully implemented due to time or technical constraints:
- Password reset and recovery
- Highlight featured items
- Promotional codes
- Pickup and delivery options
- Generate and handle refunds
- Track orders and notify status changes
- Manage supplier details
- Loyalty and rewards module
- Order confirmations and notifications/communication module
- Wastage reports

## Testing

- Use `test.php` to run basic tests.
- Validate input data and functionality across all modules.
- Check `php.errors.txt` for logged errors during development.

## License

This project is proprietary to Ravenhill Coffee. Contact the project maintainers for usage rights.

## Contact

- Email: gcsangam00@gmail.com
    : aakritiaakriti767@gmail.com
    : kunwarvishal899@gmail.com




### How to Use This File

1. Create the File:
   - Open a text editor (e.g., Notepad, VS Code, or any Markdown editor).
   - Copy the entire text above.
   - Save it as `README.md` in the root directory of your project (`C:\xampp\htdocs\project\README.md`).

2. Upload:
   - If you're using a version control system (e.g., Git), commit and push the file:
     ```bash
     git add README.md
     git commit -m "Add README.md with project documentation"
     git push origin main
     ```
   - If manually uploading via FTP or file explorer, place `README.md` in `C:\xampp\htdocs\project\`.

3. Verify:
   - Access `http://localhost/project/README.md` in a browser to ensure it renders correctly as Markdown (if your server supports it) or view it in a Markdown viewer.
