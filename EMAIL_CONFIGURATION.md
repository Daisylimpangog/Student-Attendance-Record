# 📧 Email Configuration - Development Guide

## Current Status
Your system is in **development mode**. Emails are saved to files instead of being sent (this is normal for local XAMPP).

---

## ✅ How to Test Password Reset Now

1. **Request Password Reset**
   - Go to: `http://localhost/CHPCEBU-Attendance/forgot_password.php`
   - Enter any user's email address
   - See success message

2. **View Saved Email**
   - Go to: `http://localhost/CHPCEBU-Attendance/view_emails.php`
   - Click on the email to see the password reset link
   - Copy the link and paste it in your browser

3. **Reset Password**
   - Paste the link in your browser
   - Enter new password
   - Done!

---

## 🚀 For Production (Real Email Sending)

### Option 1: Mailtrap (Easiest - Recommended)
Perfect for testing real email delivery without sending to real addresses.

**Setup:**
1. Sign up free at https://mailtrap.io
2. Go to Inbox Settings
3. Select "Integrations" → "PHP"
4. Copy the SMTP settings

**In `config.php`:**
```php
'smtp_enabled' => true,
'smtp_host' => 'smtp.mailtrap.io',
'smtp_port' => 2525,
'smtp_user' => 'your_mailtrap_user_id',
'smtp_pass' => 'your_mailtrap_password',
'use_php_mail' => false,
```

**In `includes/mail_helper.php`** (requires PHPMailer library):
```php
// Add PHPMailer implementation here
// Or install via composer: composer require phpmailer/phpmailer
```

---

### Option 2: Gmail SMTP
Send emails through your Gmail account.

**Setup Gmail:**
1. Enable 2-Factor Authentication: https://myaccount.google.com/security
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Select "Mail" and "Windows Computer"
4. Copy the 16-character password

**In `config.php`:**
```php
'email_from' => 'your-email@gmail.com',
'email_from_name' => 'CHPCEBU Attendance',
'smtp_enabled' => true,
'smtp_host' => 'smtp.gmail.com',
'smtp_port' => 587,
'smtp_user' => 'your-email@gmail.com',
'smtp_pass' => 'xxxx xxxx xxxx xxxx',  // The 16-char app password
'use_php_mail' => false,
```

---

### Option 3: AWS SES (Production Grade)
Best for high-volume email sending.

**Setup:**
1. Create AWS account and enable SES
2. Verify your domain/email
3. Get SMTP credentials from AWS console
4. Add to `config.php`

```php
'smtp_host' => 'email-smtp.region.amazonaws.com',
'smtp_port' => 587,
'smtp_user' => 'your-ses-user',
'smtp_pass' => 'your-ses-password',
```

---

### Option 4: SendGrid
Reliable service for transactional emails.

**Setup:**
1. Create SendGrid account
2. Create API key
3. Use SMTP settings from SendGrid dashboard

---

## 🔧 Update mail_helper.php for SMTP

For SMTP to work, you need PHPMailer. Install it:

```bash
composer require phpmailer/phpmailer
```

Or download from: https://github.com/PHPMailer/PHPMailer/releases

Then update `includes/mail_helper.php` to use PHPMailer instead of PHP's mail() function.

---

## ✨ Testing Workflow

### Development (Now)
```
User enters email → Email saved to file → View in view_emails.php
```

### With Mailtrap
```
User enters email → Email sent to Mailtrap → View in Mailtrap inbox → Click link
```

### Production
```
User enters email → Email sent to real inbox → User clicks link → Password reset
```

---

## 📁 File Locations

- **Saved Emails:** `storage/emails/` (development mode)
- **Email Viewer:** `view_emails.php`
- **Config:** `config.php`
- **Mail Helper:** `includes/mail_helper.php`
- **Forgot Password:** `forgot_password.php`

---

## 🎯 Quick Links

- 📧 [View Saved Emails](view_emails.php)
- 🔐 [Test Forgot Password](forgot_password.php)
- ⚙️ [System Status](forgot_password_status.php)
- 🏠 [Back to Login](index.php)

---

## ❓ FAQ

**Q: Why aren't emails being sent?**
A: Windows XAMPP doesn't have SMTP configured by default. Use development mode to test, or configure Mailtrap for testing.

**Q: How do I test emails in development?**
A: Use view_emails.php to see the password reset link that would be emailed to users.

**Q: Is it safe to use Mailtrap?**
A: Yes! Mailtrap is designed for development. Emails never reach real addresses.

**Q: Can I use this on production?**
A: Yes! Once you configure real SMTP (Gmail, SendGrid, AWS SES) with PHPMailer.

**Q: What if I forget to configure SMTP?**
A: Emails will still work in development mode (saved to files). Users get a success message, and you can view emails in view_emails.php.

---

## 🆘 Troubleshooting

**Issue:** Emails not appearing in view_emails.php
- Check storage/emails folder exists
- Check file permissions (should be 755)
- Verify no PHP errors in browser console

**Issue:** "Failed to connect to mailserver"
- Expected on Windows XAMPP - use development mode
- For production, set up SMTP in config.php

**Issue:** Gmail authentication fails
- Use App Password, not regular Gmail password
- Enable 2FA first
- Make sure 16-character password is entered correctly

**Issue:** Can't find reset link in email
- View in view_emails.php during development
- Check spam folder in Mailtrap during testing

---

**Last Updated:** November 12, 2025
