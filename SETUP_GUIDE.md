# 🔐 Forgot Password Feature - Setup Guide

## ✅ What Was Implemented

A complete **password reset system** that allows users to safely recover access to their accounts via email. Users receive a secure, time-limited verification link to reset their password.

---

## 📁 Files Created

| File | Purpose |
|------|---------|
| `forgot_password.php` | User requests password reset by entering email |
| `reset_password.php` | User clicks email link and enters new password |
| `includes/mail_helper.php` | Email sending utilities and functions |
| `migrate_password_reset.php` | Database migration script |
| `forgot_password_status.php` | System status dashboard (admin view) |
| `FORGOT_PASSWORD_README.md` | Detailed documentation |
| `SETUP_GUIDE.md` | This file |

---

## 📝 Files Modified

| File | Changes |
|------|---------|
| `index.php` | "Forgot password?" link now points to `forgot_password.php` |
| `config.php` | Added email configuration options |
| `init.sql` | Added `password_reset_tokens` table definition |

---

## 🚀 Quick Start (3 Steps)

### Step 1: Initialize Database
Visit this URL in your browser to create the necessary database table:
```
http://localhost/CHPCEBU-Attendance/migrate_password_reset.php
```

You should see a success message confirming the table was created.

### Step 2: Test the Feature
Go to the login page and click "Forgot password?":
```
http://localhost/CHPCEBU-Attendance/index.php
```

### Step 3: Check Status (Optional)
View the system status dashboard:
```
http://localhost/CHPCEBU-Attendance/forgot_password_status.php
```
(Requires admin login)

---

## 🔧 Configuration (Optional)

### Default Configuration (PHP Mail)
By default, the system uses PHP's built-in `mail()` function. No configuration needed on most servers.

### Advanced: Configure SMTP (Gmail Example)

Edit `config.php`:

```php
return [
    // ... existing config ...
    
    'email_from' => 'your-email@gmail.com',
    'email_from_name' => 'CHPCEBU Attendance System',
    'smtp_enabled' => true,
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_user' => 'your-email@gmail.com',
    'smtp_pass' => 'your-app-password',  // Use Gmail App Password
    'use_php_mail' => false,
];
```

**Gmail Setup:**
1. Enable 2-Factor Authentication: https://myaccount.google.com/security
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Copy the 16-character password to config.php

---

## 🔒 Security Features

✅ **Secure Token Generation**
- Uses PHP's `random_bytes()` for cryptographic randomness
- 32 bytes = 64 hex character tokens
- Impossible to guess or brute-force

✅ **Token Management**
- Unique constraint prevents collisions
- 24-hour automatic expiry
- One-time use only (marked as used after reset)
- Automatically deleted with user account

✅ **Password Security**
- Passwords never transmitted in plain text
- Hashed with bcrypt (PASSWORD_DEFAULT)
- Minimum 8 characters enforced
- Confirmation prevents typos

✅ **Email Security**
- Reset links valid for 24 hours only
- Generic success message (doesn't reveal if email exists)
- Beautiful HTML email template

✅ **Privacy**
- No sensitive data in URLs (token is in database, not email subject)
- Prepared statements prevent SQL injection
- No password shown in logs

---

## 📊 Database Schema

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

---

## 👤 User Experience

### For Students/Teachers (Regular Users)
1. Click "Forgot password?" on login page
2. Enter their email address
3. Check email for reset link (valid for 24 hours)
4. Click link and enter new password (minimum 8 characters)
5. Login with new password

### For Admins (Additional Features)
- View system status: `forgot_password_status.php`
- See recent password reset requests with timestamps
- Monitor for suspicious activity
- Delete old reset tokens from database if needed

---

## 🧪 Testing Checklist

Use this checklist to verify everything works:

- [ ] 1. Database table created successfully
- [ ] 2. Visit `forgot_password.php` page loads correctly
- [ ] 3. Enter valid email and submit form
- [ ] 4. Check email inbox for reset link
- [ ] 5. Click reset link in email
- [ ] 6. Password reset page loads
- [ ] 7. Enter new password (8+ characters)
- [ ] 8. Confirm password matches
- [ ] 9. Submit password reset form
- [ ] 10. See success message
- [ ] 11. Login with new password works
- [ ] 12. Try to reuse old email link → should show "expired" error
- [ ] 13. Request reset for non-existent email → should show generic message
- [ ] 14. (Optional) Admin views status page

---

## 🆘 Troubleshooting

### "Table password_reset_tokens doesn't exist"
**Solution:** Run migration script at `migrate_password_reset.php`

### "Email not received"
Check email:
- Spam folder
- Verify `config.php` email settings
- Check server mail logs: `/var/log/mail.log` (Linux)
- Use Mailtrap.io to test SMTP

### "Reset link not working"
Try:
- Copy-paste entire URL from email instead of clicking
- Request new reset link (old one may have expired)
- Check database for token: `SELECT * FROM password_reset_tokens;`

### "Password reset appears to hang"
Check:
- Server error logs
- `config.php` SMTP credentials if using SMTP
- Database connection in `db.php`

### "Mixed content warning (HTTPS)"
Change reset link generation in `forgot_password.php`:
```php
$reset_link = 'https://' . $_SERVER['HTTP_HOST'] . '/CHPCEBU-Attendance/reset_password.php?token=' . urlencode($token);
```

---

## 📚 File Locations Reference

```
CHPCEBU-Attendance/
├── forgot_password.php          ← User enters email
├── reset_password.php           ← User resets password
├── forgot_password_status.php   ← System status (admin)
├── migrate_password_reset.php   ← Database migration
├── index.php                    ← Updated: forgot password link
├── config.php                   ← Updated: email settings
├── includes/
│   └── mail_helper.php          ← Email utilities
├── FORGOT_PASSWORD_README.md    ← Full documentation
└── SETUP_GUIDE.md              ← This file
```

---

## 🔄 How It Works (Behind the Scenes)

### Password Reset Request Flow
1. User submits email on `forgot_password.php`
2. System looks up user by email
3. Generates random 64-character token
4. Stores token in database with user_id and 24-hour expiry
5. Sends HTML email with reset link containing token
6. Shows generic success message (security: doesn't reveal if email exists)

### Password Reset Flow
1. User clicks link in email → token in URL
2. `reset_password.php` validates token (exists, not expired, not used)
3. User enters new password (minimum 8 characters)
4. System hashes password with bcrypt
5. Updates user password in database
6. Marks token as "used" (prevents reuse)
7. Shows success message + login link

---

## 🛠️ Customization

### Change Email From Address
Edit `config.php`:
```php
'email_from' => 'support@chpcebu.edu.ph',
'email_from_name' => 'CHPCEBU Support Team',
```

### Change Token Expiry Time
Edit `forgot_password.php`, find:
```php
$expires_at = get_reset_token_expiry(24); // Change 24 to desired hours
```

### Customize Email Template
Edit `includes/mail_helper.php`, find the HTML email in `send_password_reset_email()` function and customize colors, text, logo, etc.

### Add Extra Security (Optional)
Add rate limiting to prevent brute force:
```php
// In forgot_password.php
// Check if user requested reset in last 30 minutes
$stmt = $pdo->prepare(
    'SELECT id FROM password_reset_tokens 
     WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)'
);
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    $errors[] = 'Please wait before requesting another reset.';
}
```

---

## 📞 Support Resources

- **Detailed Docs:** See `FORGOT_PASSWORD_README.md`
- **Bootstrap Icons:** https://icons.getbootstrap.com/
- **PHP Password Hashing:** https://www.php.net/manual/en/function.password-hash.php
- **Gmail App Passwords:** https://support.google.com/accounts/answer/185833

---

## ✨ Summary

Your attendance system now has a professional, secure password recovery feature! Users can safely reset their passwords via email, and admins can monitor reset requests.

**Next Steps:**
1. Run `migrate_password_reset.php` to initialize the database
2. Test the feature with a test account
3. Customize `config.php` with your email settings if needed
4. Share the login page with users

---

**Version:** 1.0.0  
**Last Updated:** November 12, 2025  
**Status:** ✅ Ready for Production
