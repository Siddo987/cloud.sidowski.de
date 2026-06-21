# Datei Wolke

A secure, feature-rich file management and sharing platform built with PHP, offering advanced authentication mechanisms and multi-language support.

## Features

### Security
- **User Authentication** - Secure login system with email verification
- **Two-Factor Authentication (2FA)** - TOTP and backup codes support
- **WebAuthn Support** - Passwordless authentication using WebAuthn/FIDO2
- **Password Management** - Secure password reset and change functionality
- **Email Verification** - Email confirmation for account security

### File Management
- **File Upload & Download** - Easy file uploading and downloading
- **Advanced File Sharing** - Share files publicly, restrict to logged-in users, or grant access to specific users only
- **File Organization** - Organize files by folders
- **Trash & Auto-Cleanup** - Recover deleted files. Trash is automatically emptied after 90 days
- **User-specific Storage** - Isolated file storage per user

### User Management
- **User Registration** - Self-service account creation
- **User Profiles** - Manage user information and preferences
- **Admin Dashboard** - Monitor all users and files
- **Debug Dashboard** - Identify and restore physical orphan files (files without DB entry)
- **User Impersonation** - Admin capability to access user accounts for support

### Internationalization
- **Multi-language Support** - German (de) and English (en) localization
- **Dynamic Language Switching** - Easy language selection

## Requirements

- PHP 7.4 or higher
- Composer for dependency management
- A web server (Apache, Nginx, etc.)
- Database (as configured in config files)
- Modern browser with WebAuthn support (for passwordless authentication)

## Installation (Docker / Recommended)

1. Clone the repository to your server:
   ```bash
   cd /path/to/cloud.sidowski.de
   ```

2. Build and start the Docker containers:
   ```bash
   docker-compose build
   docker-compose up -d
   ```
   *Note: The included Dockerfile automatically installs and configures a cron daemon that runs nightly cleanup tasks (e.g., removing files older than 90 days).*

## Installation (Manual)

1. Clone the repository or extract the files to your web server directory:
   ```bash
   cd /path/to/cloud.sidowski.de
   ```

2. Install PHP dependencies using Composer:
   ```bash
   composer install
   ```

3. Configure the application by editing the configuration files in the `config/` directory:
   - `config.php` - Main configuration
   - `mail_config.php` - Email settings
   - `bootstrap.php` - Application bootstrap settings

4. Apply database migrations:
   ```bash
   php scripts/apply_migrations.php
   ```

5. Set up proper file permissions for the `user_uploads/` directory:
   ```bash
   chmod 755 user_uploads/
   ```

6. Configure your web server to serve the application from the root directory.

## Configuration

### Database
Configure your database connection in `config/config.php`.

### Email
Set up SMTP or mail settings in `config/mail_config.php` for password resets and email verification.

### Two-Factor Authentication
- TOTP (Time-based One-Time Password) - Generate codes using authenticator apps
- Backup Codes - Fallback codes for account recovery
- WebAuthn - Hardware security key or platform authenticator support

## File Structure

```
config/              - Configuration files
css/                 - Stylesheets
de/                  - German language pages
en/                  - English language pages
includes/            - Shared PHP includes (headers, footers, navigation)
js/                  - JavaScript files
db/                  - Database migrations
scripts/             - Utility scripts (migrations, setup)
user_uploads/        - User file storage
vendor/              - Composer dependencies
```

## Usage

### For Users
1. Register a new account or login
2. Set up Two-Factor Authentication for enhanced security
3. Upload and organize your files
4. Share files publicly or with other users
5. Manage your profile and security settings

### For Administrators
1. Access the admin dashboard
2. Manage users and permissions
3. Monitor file storage
4. Impersonate users for support purposes
5. Manage system settings

## Security Best Practices

- Always enable Two-Factor Authentication
- Regularly update passwords
- Use WebAuthn for passwordless, more secure authentication
- Keep your backup codes in a safe place
- Monitor your account activity through the dashboard

## Support

For issues, feature requests, or bug reports, please contact the development team.

## License

[Specify your license here]

---

**Version**: 1.1  
**Last Updated**: 2026-06-21
