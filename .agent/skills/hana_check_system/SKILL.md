---
name: Hana Check System Expert
description: Specialist in maintaining and extending the Online Check Sheet System for Hana Project.
---

# Hana Check System Maintenance & Development Guide

This skill provides comprehensive instructions for any AI agent working on the Hana Online Check Sheet System. It ensures consistency in design, security, and architecture.

## 1. Technlogy Stack
- **Core**: PHP 8.1+ (Procedural with PDO)
- **Database**: MariaDB / MySQL (using `check_sheet_db`)
- **Frontend**: Bootstrap 5, FontAwesome 6, SweetAlert2 (for notifications)
- **Styling**: Vanilla CSS with custom design tokens in `assets/css/style.css`
- **URLs**: Clean URLs handled by `.htaccess` (Extentionless)

## 2. Directory Structure
- `/includes`: Header and Footer fragments (contains session logic)
- `/assets/css`: Main design system
- `/uploads`: Storage for machine/tool images
- `config.php`: Central database connection (using PDO)
- `save_*.php`: Data handling and logic files

## 3. Core Logic & Security
- **Authentication**: Session-based. Redirects to `login` if unauthorized.
- **Session Timeout**: 30 minutes of inactivity (handled in `header.php`).
- **User Roles**: 
    - `admin`: Full access + User Management
    - `leader`: View and approval logic
    - `Technicien`: Maintenance and Downtime handling
    - `user`: Standard data entry (Operator)
- **Data Safety**: ALWAYS use PDO Prepared Statements to prevent SQL Injection.
- **Notifications**: USE SweetAlert2 (Toast for success, Modal for errors/confirmations).

## 4. Design Guidelines
- **Primary Color**: `#4f46e5` (Indigo)
- **Sidebar**: Collapsible (Desktop: Icon only, Mobile: Hidden).
- **Cards**: Use `.card-premium` class for consistent shadow and padding.
- **Buttons**: Rounded-pill for primary actions, Rounded-circle for icon-only actions.

## 5. Common Procedures
### Adding a New Data Entry Page:
1. Create the `.php` file (e.g., `new_module.php`).
2. Include `config.php` and `header.php`.
3. Use `footer.php` at the end.
4. Create a `save_*.php` handler that redirects back with `?success=1` or `?error=1`.

### Modifying Database:
1. Update `config.php` if new constants are needed.
2. Provide raw SQL commands for table updates in the implementation plan.

## 6. Maintenance Commands
- Check DB connection: `php -r "require 'config.php'; echo 'Success';" `
- List all routes: `grep -r "href=" . --include="*.php"`
