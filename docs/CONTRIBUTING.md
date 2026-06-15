# CONTRIBUTING

# 🤝 Contributing to Vercodea Auth Core

First off, thank you for considering contributing to Vercodea Auth Core! 🎉 

Your contributions help make authentication more secure and accessible for everyone.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Security Guidelines](#security-guidelines)
- [Pull Request Process](#pull-request-process)
- [Testing Requirements](#testing-requirements)
- [Documentation Guidelines](#documentation-guidelines)
- [Reporting Bugs](#reporting-bugs)
- [Feature Requests](#feature-requests)
- [Security Vulnerabilities](#security-vulnerabilities)
- [Questions & Support](#questions--support)
- [Support & Sponsorship](#support--sponsorship)

---

## Code of Conduct

**Key principles:**
- Be respectful and inclusive
- Accept constructive criticism
- Focus on what's best for the community
- Show empathy towards other contributors

---

## Getting Started

### Prerequisites

| Requirement | Version | Verification |
|-------------|---------|--------------|
| PHP | 7.4+ or 8.0+ | `php -v` |
| MySQL | 5.7+ | `mysql --version` |
| Redis | 5.0+ | `redis-cli --version` |
| Composer | 1.9+ | `composer --version` |

### Fork & Clone

```bash
# 1. Fork the repository on GitHub

# 2. Clone your fork
git clone https://github.com/YOUR_USERNAME/auth-core.git
cd auth-core

# 3. Add upstream remote
git remote add upstream https://github.com/vercodea/auth-core.git

# 4. Install dependencies
composer install

# 5. Copy environment configuration
cp .env.example .env

# 6. Configure your .env file
# Edit .env with your local database/Redis credentials

# 7. Run database setup
php -r "require 'vendor/autoload.php'; require 'src/auth_init.php'; AuthInit::init();"

# 8. Run tests (when available)
composer test
```

---

## Development Setup

### Local Environment Configuration

Create a `.env.local` file for your development environment:

```env
# Database
MYSQL_HOST=127.0.0.1
MYSQL_PORT=3306
MYSQL_USERNAME=auth_dev
MYSQL_PASSWORD=dev_password
MYSQL_DBNAME=auth_dev

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# API Keys (use test keys)
OTP_API_KEY=test_xxxxxxxxxxxxxxxxxxxxxxxx
SEND_OTP_URL=https://api.resend.com/emails

# Environment
APP_ENV=development
SESSION_COOKIE_SECURE=false
SESSION_COOKIE_HTTPONLY=true
SESSION_COOKIE_SAMESITE=Strict

# Logging
LOG_DIR=./logs
```

### Database Setup for Development

```bash
# Create development database
mysql -u root -p -e "CREATE DATABASE auth_dev;"

# Run migrations
php -r "require 'vendor/autoload.php'; require 'src/auth_init.php'; AuthInit::init();"

# Verify tables created
mysql -u root -p auth_dev -e "SHOW TABLES;"
```

### File Structure for Contributors

```
auth-core/
├── src/
│   ├── auth_init.php                 # Main public API
│   ├── start_system.php              # Database initialization
│   ├── Query/
│   │   ├── Query_commands/           # SQL query files
│   │   └── query_loader.php          # Query loader
│   ├── config/
│   │   ├── config_env.php            # Environment config
│   │   ├── db.php                    # Database connection
│   │   └── logs.php                  # Logging utility
│   ├── middleware/
│   │   ├── ratelimit.php             # Rate limiting
│   │   ├── network_check.php         # VPN/Proxy detection
│   │   ├── email_otp_verifier.php    # OTP verification
│   │   ├── session_manager.php       # Session handling
│   │   ├── start_system.php          # System initialization
│   │   ├── file_access_lock/
│   │   │   └── gateway_locker.php    # Security gate
│   │   └── otp_manager/
│   │       ├── otp_mailer.php        # Email delivery
│   │       ├── message_loader.php    # Template loader
│   │       └── Otp_messages/
│   │           └── messages/
│   │               ├── otp_code_msg.html         # OTP email template
│   │               ├── password_recovery.html    # Recovery link template
│   │               └── access_bridge_msg.html    # Lockout alert template
│   ├── security-checks_tools/
│   │   ├── common_passwords.txt      # 94,500 weak passwords
│   │   └── reserved_passwords.txt    # Reserved words list
│   └── src/
│       ├── signup.php                # Registration handler
│       ├── signin.php                # Login handler
│       ├── logout.php                # Logout handler
│       ├── otp_auth.php              # OTP sender
│       └── account_recover.php       # Password recovery
├── tests/                            # Unit tests (PHPUnit)
├── docs/                             # Additional documentation
├── composer.json                     # Project metadata
├── phpunit.xml                       # Test configuration
├── .env.example                      # Environment template
└── README.md                         # Main documentation
```

---

## Coding Standards

### PHP Code Style

We follow **PSR-12** coding standards:

```php
<?php

namespace Vercodea\AuthCore;

// 1. Namespacing - Always use namespaces
class AuthInit {
    
    // 2. Indentation - 4 spaces (never tabs)
    public function authenticate($username, $password) {
        if (empty($username)) {
            return ['status' => false, 'msg' => 'Username required'];
        }
        
        // 3. Camel case for methods and variables
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // 4. Constants in UPPER_SNAKE_CASE
        define('MAX_LOGIN_ATTEMPTS', 5);
        
        return ['status' => true, 'msg' => 'Success'];
    }
    
    // 5. Access modifiers always specified (public, private, protected)
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
```

### Key Standards

| Rule | Example |
|------|---------|
| **Namespace** | `namespace Vercodea\AuthCore;` |
| **Class names** | `PascalCase` - `AuthInit`, `DatabaseManager` |
| **Method names** | `camelCase` - `loginUser()`, `validateEmail()` |
| **Variable names** | `camelCase` - `$userName`, `$isValid` |
| **Constants** | `UPPER_SNAKE_CASE` - `MAX_ATTEMPTS`, `OTP_EXPIRES` |
| **Indentation** | 4 spaces (no tabs) |
| **Line length** | Max 120 characters |
| **Comments** | `// Single line` or `/* Multi-line */` |

### Code Quality Tools

```bash
# Run PHP CodeSniffer (PSR-12 check)
vendor/bin/phpcs src/ --standard=PSR12

# Auto-fix code style
vendor/bin/phpcbf src/ --standard=PSR12

# Run PHPStan static analysis
vendor/bin/phpstan analyse src/

# Run Psalm (another static analysis tool)
vendor/bin/psalm
```

---

## Security Guidelines

### SQL Injection Prevention

✅ **Always use prepared statements:**

```php
// ✅ CORRECT - Prepared statement
$stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$stmt->execute([$email]);

// ❌ WRONG - String concatenation
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = '$email'");
```

### Password Handling

✅ **Use bcrypt via PASSWORD_DEFAULT:**

```php
// ✅ CORRECT - bcrypt with PASSWORD_DEFAULT
$hash = password_hash($password, PASSWORD_DEFAULT);
$isValid = password_verify($password, $hash);

// ❌ WRONG - MD5 or SHA1
$hash = md5($password);
```

### CSRF Protection

✅ **Always validate CSRF tokens:**

```php
// ✅ CORRECT - CSRF token validation
if (!hash_equals($_SESSION['csrf_token'], $_POST['token'])) {
    http_response_code(403);
    exit('CSRF token validation failed');
}
```

### Input Validation

✅ **Always validate and sanitize input:**

```php
// ✅ CORRECT - Validation before database
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return ['status' => false, 'msg' => 'Invalid email'];
}

// ✅ CORRECT - Type checking
if (!is_string($username) || strlen($username) < 3) {
    return ['status' => false, 'msg' => 'Invalid username'];
}
```

### Never Log Sensitive Data

```php
// ✅ CORRECT - Safe logging
log_activity("User $username logged in from $ip");

// ❌ WRONG - Logging passwords or tokens
log_activity("User $username logged in with password: $password");
```

### Security Checklist for PRs

- [ ] No SQL injection vulnerabilities (prepared statements only)
- [ ] No hardcoded API keys or secrets
- [ ] No sensitive data in logs
- [ ] CSRF tokens validated on state-changing operations
- [ ] Input validation on all user data
- [ ] No direct file inclusion (LFI/RFI)
- [ ] Password hashing using PASSWORD_DEFAULT
- [ ] Error messages don't expose system details

---

## Email Template Development

### Email Templates Location

All email templates are located in: `src/middleware/otp_manager/Otp_messages/messages/`

### Template Files

| Template | Purpose | Variables |
|----------|---------|-----------|
| `otp_code_msg.html` | OTP verification code | `{$otp_code}` |
| `password_recovery.html` | Magic link password recovery | `{$reset_url}` |
| `access_bridge_msg.html` | Account lockout alert | `{$ip_address}`, `{$blocked_at}`, `{$block_expires}` |

### Template Guidelines

**When modifying or creating email templates:**

1. **Use semantic HTML** - Use proper HTML5 structure
2. **Inline CSS** - All styles must be inline (email client compatibility)
3. **Responsive Design** - Test on mobile and desktop
4. **Accessible** - Use proper color contrast, alt text for images
5. **No JavaScript** - Email clients don't support scripts
6. **Variable Syntax** - Use `{$variable_name}` for substitution
7. **No External Resources** - Avoid external CSS/JS/images

### Template Example

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px;">
        <h1 style="color: #333; font-size: 24px; margin-bottom: 16px;">Verification Code</h1>
        <p style="color: #666; font-size: 14px; line-height: 1.6;">
            Your verification code is: <strong>{$otp_code}</strong>
        </p>
        <p style="color: #999; font-size: 12px;">
            This code expires in 5 minutes.
        </p>
    </div>
</body>
</html>
```

### Testing Email Templates

```bash
# 1. Test in development with Resend sandbox
OTP_API_KEY=resend_sandbox_key

# 2. Check email rendering in different clients
# - Gmail, Outlook, Apple Mail, etc.

# 3. Validate HTML
# - W3C Markup Validator
# - Email-specific linters
```

---

## Middleware Development

### Middleware Architecture

The authentication system uses a **pipeline middleware** architecture:

```
Request → Gateway Check → Network Check → Rate Limit → Auth → Session → Response
```

### Creating New Middleware

**Location:** `src/middleware/`

**Template:**
```php
<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/file_access_lock/gateway_locker.php';

// ✅ ALWAYS verify pipeline access first
verify_pipeline_access(['your_middleware.php', 'auth_init.php']);

function your_middleware_function() {
    try {
        // Your logic here
        log_activity("Middleware executed successfully");
        return true;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return false;
    }
}
?>
```

### Middleware Best Practices

1. **Always verify pipeline access** - Prevent unauthorized execution
2. **Use try-catch blocks** - Handle exceptions gracefully
3. **Log activity** - Use `log_activity()` for audit trails
4. **Return standardized format** - Return `['status' => bool, 'msg' => string]`
5. **Respect rate limits** - Don't bypass existing security controls
6. **Handle Redis errors** - Check Redis connection status
7. **Document configuration** - Add env variables to README

### Middleware Testing

```bash
# Test middleware in isolation
php -r "require 'src/middleware/your_middleware.php'; var_dump(your_function());"

# Test with mock data
php -r "
    \$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    require 'src/middleware/network_check.php';
    var_dump(check_ip());
"
```

---

## Pull Request Process

### Before You Start

1. **Create an issue** - Discuss major changes first
2. **Check existing PRs** - Avoid duplicate work
3. **Create a branch** - `git checkout -b feature/your-feature-name`

### Making Changes

```bash
# 1. Create feature branch
git checkout -b feature/amazing-feature

# 2. Make your changes
# Edit files, test locally

# 3. Commit with clear messages
git add .
git commit -m "feat: Add amazing feature description"
# Use conventional commits: feat:, fix:, docs:, test:, refactor:, chore:

# 4. Keep branch updated
git fetch upstream
git rebase upstream/main

# 5. Push to your fork
git push origin feature/amazing-feature
```

### PR Submission Checklist

Before submitting a PR, ensure:

- [ ] **Code follows PSR-12** - Run `phpcs` and `phpcbf`
- [ ] **Tests pass** - Run `composer test`
- [ ] **No security issues** - Check security guidelines
- [ ] **Documented** - Added/updated comments and docblocks
- [ ] **Single responsibility** - PR focuses on one feature/fix
- [ ] **Clean history** - No merge commits or debug prints
- [ ] **Updated README** - If adding new public methods
- [ ] **No hardcoded values** - Use environment variables

### PR Description Template

```markdown
## Description
Brief description of what this PR does

## Type of Change
- [ ] Bug fix (non-breaking change)
- [ ] New feature (non-breaking change)
- [ ] Breaking change
- [ ] Documentation update

## Related Issue
Closes #123

## Changes Made
- Change 1
- Change 2

## Testing
How to test these changes:
1. Step 1
2. Step 2

## Screenshots (if applicable)
Attach images/videos

## Checklist
- [ ] Code follows PSR-12
- [ ] Tests added/updated
- [ ] Documentation updated
- [ ] No security issues
```

### Review Process

Your PR will be reviewed for:
1. **Code quality** - Follows standards and best practices
2. **Security** - No vulnerabilities or security issues
3. **Testing** - Adequate test coverage
4. **Documentation** - Clear comments and docblocks
5. **Functionality** - Works as intended

**Feedback and changes are expected.** We iterate together to get the best result! 🚀

---

## Testing Requirements

### Unit Tests with PHPUnit

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/AuthInitTest.php

# Run with coverage report
vendor/bin/phpunit --coverage-html=coverage/
```

### Test File Structure

```php
<?php

namespace Vercodea\AuthCore\Tests;

use PHPUnit\Framework\TestCase;
use Vercodea\AuthCore\AuthInit;

class AuthInitTest extends TestCase {
    
    private $auth;
    
    protected function setUp(): void {
        $this->auth = new AuthInit();
    }
    
    public function testAuthLoginSuccess() {
        $result = $this->auth->auth_login('testuser', null, 'TestPass123!');
        
        $this->assertTrue($result['status']);
        $this->assertStringContainsString('successful', $result['msg']);
    }
    
    public function testAuthLoginFailure() {
        $result = $this->auth->auth_login('nonexistent', null, 'WrongPass123!');
        
        $this->assertFalse($result['status']);
        $this->assertStringContainsString('Invalid', $result['msg']);
    }
}
```

### Required Coverage

- **Minimum 80%** code coverage required
- **All public methods** must have test cases
- **Error paths** must be tested
- **Edge cases** must be covered

---

## Documentation Guidelines

### Code Comments

```php
<?php

/**
 * Authenticates a user with username/email and password.
 *
 * @param string|null $username Username (null if using email)
 * @param string|null $email    Email address (null if using username)
 * @param string      $password Plain text password
 *
 * @return array ['status' => bool, 'msg' => string]
 *
 * @example
 * $result = AuthInit::auth_login('johndoe', null, 'SecurePass123!');
 * if ($result['status']) {
 *     echo "Login successful";
 * }
 */
public function auth_login($username, $email, $password) {
    // Implementation here
}
```

### Docblock Standards

- **@param** - Parameter type and description
- **@return** - Return type and description
- **@throws** - Exceptions thrown
- **@example** - Usage example
- **@deprecated** - If method is deprecated
- **@see** - Related methods/links

### README Updates

When adding new features:

1. **Update Table of Contents** if adding new sections
2. **Add to Features list** - Brief description
3. **Add API example** - How to use it
4. **Update roadmap** - If applicable
5. **Add security notes** - If relevant

---

## Reporting Bugs

### Bug Report Template

**Title:** Concise description of the bug

```markdown
## Description
Clear description of the bug

## Environment
- PHP Version: 7.4
- MySQL Version: 5.7
- Redis Version: 5.0
- OS: Ubuntu 20.04

## Steps to Reproduce
1. Step 1
2. Step 2
3. Step 3

## Expected Behavior
What should happen

## Actual Behavior
What actually happens

## Error Log Output
```
[2024-06-13 10:00:00] [ERROR] Error message here
```

## Screenshots
If applicable, add screenshots

## Possible Solution
If you have ideas on how to fix it
```

### Security Bugs

**DO NOT open a public issue for security vulnerabilities.** See [Security Vulnerabilities](#security-vulnerabilities).

---

## Feature Requests

### Feature Request Template

**Title:** Brief description of requested feature

```markdown
## Feature Description
What does this feature do?

## Motivation
Why is this feature needed?

## Use Cases
When/how would this be used?

## Proposed Solution
How should it work?

## Alternatives Considered
Other approaches?

## Additional Context
Any other info
```

### Feature Evaluation

Features are evaluated based on:
1. **Community demand** - Number of upvotes/requests
2. **Scope** - Fits within project goals
3. **Security** - Doesn't compromise security
4. **Performance** - No negative impact
5. **Maintenance** - Long-term maintainability

---

## Security Vulnerabilities

### Responsible Disclosure

If you discover a **security vulnerability**, please:

1. **DO NOT** open a public GitHub issue
2. **DO NOT** disclose it publicly
3. **Email** vercodea@gmail.com with details:
   - Type of vulnerability
   - Location in code
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

### Security Response Timeline

- **24 hours** - Acknowledgment of report
- **7 days** - Initial assessment
- **30 days** - Patch released (private)
- **Public disclosure** - After patch release and user notification

---

## Questions & Support

### Getting Help

- **Documentation Issues** - Create issue or PR
- **Usage Questions** - Start a [discussion on GitHub](https://github.com/vercodea/auth-core/discussions)
- **Bug Reports** - Open a [GitHub issue](https://github.com/vercodea/auth-core/issues)
- **Security Issues** - Email vercodea@gmail.com
- **General Feedback** - [GitHub Discussions](https://github.com/vercodea/auth-core/discussions)

### Communication Channels

| Channel | Purpose |
|---------|---------|
| **GitHub Issues** | Bug reports and feature requests |
| **GitHub Discussions** | Questions, ideas, general chat |
| **GitHub PRs** | Code contributions |
| **Email** | Security vulnerabilities & partnerships |
| **Discord** | Real-time community support |
| **Twitter** | News and updates |
| **Stack Overflow** | Tag with `vercodea-auth-core` |

---

## Thank You!

Your contributions are what make this project great. Whether it's code, documentation, bug reports, or ideas — **every contribution matters!** 💪

**Happy Contributing!** 🎉

---

---

## Support & Sponsorship

### Help Us Improve Auth Core

Vercodea Auth Core is maintained by the community. If this project has helped you, consider supporting its development:

### 🎯 Sponsorship Options

#### Direct Support
- **GitHub Sponsors** - [Sponsor us on GitHub](https://github.com/sponsors/vercodea)
- **Buy Me a Coffee** - [Support via Buy Me a Coffee](https://buymeacoffee.com/vercodea)

- **Crypto** - Bitcoin, Ethereum, and other cryptocurrencies accepted -email akansoprince@gmail.com for address

#### Corporate Sponsorship
For enterprise support, consulting, or custom development:

📧 **Email:** vercodea@gmail.com
🔗 **Website:** coming soon

### 🏆 Sponsor Benefits

| Tier | Amount | Benefits |
|------|--------|----------|
| **Bronze** | $5/month | Badge in README, early access to features |
| **Silver** | $25/month | All Bronze benefits + Priority support |
| **Gold** | $100/month | All Silver benefits + Logo on website, consulting hours |
| **Platinum** | $500+/month | All Gold benefits + Custom feature priority, dedicated support |

### 🙏 Ways to Support (No Cost!)

Even if you can't sponsor financially, you can still help:

- ⭐ **Star the repository** - Increases visibility
- 📢 **Share & promote** - Tell others about Auth Core
- 🐛 **Report bugs** - Help improve quality
- ✍️ **Write documentation** - Improve guides
- 🧪 **Contribute code** - Add features and fixes
- 💬 **Answer questions** - Help other community members

### 📞 Community Forum

Join our growing community:

- **GitHub Discussions** - [Ask questions and share ideas](https://github.com/vercodea/auth-core/discussions)
- **Discord Server** - [Chat with developers](https://discord.gg/vercodea)
- **Twitter** - [@vercodea](https://twitter.com/vercodea)
- **Stack Overflow** - Tag questions with `vercodea-auth-core`

### 🎁 Acknowledgments

We recognize all contributors in:
- GitHub Contributors page - Automatic tracking

**Every contribution, big or small, is appreciated!** 🙌

---

## 📝 License

By contributing, you agree that your contributions will be licensed under the same [MIT License](../LICENCE) as the project.