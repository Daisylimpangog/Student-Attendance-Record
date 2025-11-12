# Forgot Password Feature Documentation

## Overview
This feature allows users to reset their passwords via email. Users receive a verification link with a unique token that expires after 24 hours.

## Files Created/Modified

### New Files
1. **forgot_password.php** - User requests password reset by entering email
2. **reset_password.php** - User clicks email link and enters new password
3. **includes/mail_helper.php** - Email utility functions
4. **migrate_password_reset.php** - Database migration script
5. **FORGOT_PASSWORD_README.md** - This file

### Modified Files
1. **index.php** - "Forgot password?" link now points to `forgot_password.php`
2. **config.php** - Added email configuration options
3. **init.sql** - Added `password_reset_tokens` table definition

## Database Schema

### password_reset_tokens Table
```sql
CREATE TABLE password_reset_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(255) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX token_idx (token),
  INDEX email_idx (email)
)
```

## Installation

### Step 1: Update Database
Run the migration script to create the `password_reset_tokens` table:
```
Navigate to: http://localhost/CHPCEBU-Attendance/migrate_password_reset.php
```

Or manually execute this SQL in your database:
```sql
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `token_idx` (`token`),
  INDEX `email_idx` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Step 2: Configure Email Settings (config.php)

The system uses PHP's built-in `mail()` function by default. Configure in `config.php`:

```php
'email_from' => 'noreply@chpcebu.edu.ph',
'email_from_name' => 'CHPCEBU Attendance System',
'use_php_mail' => true,
'smtp_enabled' => false, // Set to true if using SMTP
'smtp_host' => 'smtp.mailtrap.io',
'smtp_port' => 2525,
'smtp_user' => '',
'smtp_pass' => '',
```

### Step 3: Server Requirements

For email functionality, ensure:
- PHP mail() function is enabled on your server
- OR configure SMTP settings for your email provider
- Sendmail or Postfix configured on Linux/Unix servers
- SMTP service running on Windows servers

## Email Configuration Options

### Option 1: PHP Mail Function (Default - Windows/Linux)
No additional configuration needed. Emails are sent using the server's default mail system.

### Option 2: SMTP (Gmail Example)
```php
'email_from' => 'your-email@gmail.com',
'smtp_enabled' => true,
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => 587,
'smtp_user' => 'your-email@gmail.com',
'smtp_pass' => 'your-app-password', // Use Gmail App Password, not your regular password
```

### Option 3: Mailtrap (Testing/Development)
```php
'smtp_enabled' => true,
'smtp_host' => 'smtp.mailtrap.io',
'smtp_port' => 2525,
'smtp_user' => 'your-mailtrap-user',
'smtp_pass' => 'your-mailtrap-pass',
```

## User Flow

### Request Password Reset
1. User clicks "Forgot password?" on login page
2. User is taken to `/forgot_password.php`
3. User enters their email address
4. System generates a unique 64-character token
5. Token is stored in database with 24-hour expiry
6. Email is sent with reset link containing token
7. User sees success message (same for valid/invalid emails for security)

### Reset Password
1. User clicks link in email
2. System verifies token is valid and not expired
3. User enters new password (minimum 8 characters)
4. Password is hashed and updated in database
5. Token is marked as "used" to prevent reuse
6. User is redirected to login with success message

## Security Features

✅ **Token Security**
- 32 bytes (64 hex characters) of cryptographic randomness
- Unique database constraint prevents token collision
- Tokens expire after 24 hours
- One-time use: tokens marked as "used" after password reset

✅ **Password Security**
- Passwords hashed with `PASSWORD_DEFAULT` (bcrypt)
- Minimum 8 characters enforced
- Password confirmation prevents typos
- No password shown in URLs or logs

✅ **Email Security**
- Email addresses validated before token generation
- Reset links expire after 24 hours
- Email does not reveal if account exists (generic message shown)
- Expired/used tokens show error messages

✅ **Database Security**
- Foreign key constraints prevent orphaned tokens
- Tokens automatically deleted with user account
- Prepared statements prevent SQL injection
- CSRF protection through standard session handling

## Testing Checklist

- [ ] 1. Request password reset with valid email
- [ ] 2. Check database for token record
- [ ] 3. Check email for reset link (may be in spam)
- [ ] 4. Click reset link
- [ ] 5. Enter new password and confirm
- [ ] 6. Login with new password
- [ ] 7. Try to reuse old reset link (should fail)
- [ ] 8. Try reset with expired token (after 24+ hours)
- [ ] 9. Request reset for non-existent email (should show generic message)
- [ ] 10. Try weak password (less than 8 characters)
- [ ] 11. Try mismatched passwords

## Troubleshooting

### Emails Not Sending

**Windows XAMPP:**
- Check `php.ini` for SMTP settings
- Use SMTP with credentials instead of local mail

**Linux/Ubuntu:**
- Check Sendmail: `sudo systemctl status sendmail`
- Check Postfix: `sudo systemctl status postfix`
- Check mail logs: `tail -f /var/log/mail.log`

**General:**
- Check PHP error logs: `tail -f /var/log/php_errors.log`
- Enable debugging in `mail_helper.php` to see error logs
- Test with Mailtrap.io first (free testing service)

### Database Error: Table Doesn't Exist
- Run migration script: `migrate_password_reset.php`
- Or manually create table using SQL above

### Reset Link Not Working
- Check if token is in database: `SELECT * FROM password_reset_tokens;`
- Verify token hasn't expired: `SELECT *, NOW(), expires_at FROM password_reset_tokens;`
- Check if token has been used: `SELECT * FROM password_reset_tokens WHERE used=1;`

### "Invalid or Expired Reset Link"
- Token may be corrupted in URL. Try resending reset email.
- Token may have expired. Request a new password reset.
- Token may have already been used. Request a new password reset.

## Advanced Customization

### Change Token Expiry Time
In `forgot_password.php`, find this line:
```php
$expires_at = get_reset_token_expiry(24); // 24 hours
```
Change `24` to desired hours (e.g., `48` for 2 days)

### Customize Email Template
Edit email HTML in `includes/mail_helper.php`:
- Change colors, logo, text, company name
- Add/remove sections as needed
- Keep the `$reset_link` variable in the template

### Add Additional Validation
In `forgot_password.php`, add custom validations:
```php
// Example: Only allow resets for student accounts
$user = $stmt->fetch();
if ($user && $user['kind'] !== 'student') {
    // Additional check logic
}
```

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review error logs in browser console and server logs
3. Verify all files were created correctly
4. Test email configuration with Mailtrap or similar service

---
**Last Updated:** November 12, 2025
**Version:** 1.0.0
