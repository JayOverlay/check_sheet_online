#!/bin/bash
# Check if logs directory exists, if not create it
mkdir -p /Applications/XAMPP/xamppfiles/htdocs/check_sheet_online/logs

# Execute the PHP script using XAMPP's PHP binary
/Applications/XAMPP/xamppfiles/bin/php /Applications/XAMPP/xamppfiles/htdocs/check_sheet_online/scripts/daily_report.php >> /Applications/XAMPP/xamppfiles/htdocs/check_sheet_online/logs/daily_report.log 2>&1
