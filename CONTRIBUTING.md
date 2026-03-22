# Contributing to AI Gateway for WordPress

Thank you for your interest in contributing to AI Gateway. This document explains how to set up your development environment and submit changes.

## Prerequisites

- PHP 8.0 or higher
- [Composer](https://getcomposer.org/) (dependency manager for PHP)
- A local WordPress installation (6.0+)

## Setup

1. Clone the repository into your WordPress plugins directory:

   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/theduyet/ai-gateway.git
   cd ai-gateway
   ```

2. Install PHP dependencies:

   ```bash
   composer install
   ```

3. Configure PHPStan for your local WordPress path. Edit `phpstan.neon` and update `scanDirectories` to point to your local WordPress installation:

   ```neon
   parameters:
     scanDirectories:
       - /path/to/your/wordpress/
   ```

4. Activate the plugin in your local WordPress admin.

## Coding Standards

- **Autoloading:** PSR-4 with the `AI_Gateway\` namespace
- **Style:** WordPress coding standards (WPCS) -- tabs for indentation, `snake_case` for function names
- **Documentation:** PHPDoc blocks on all public methods
- **Error handling:** Return `WP_Error` objects for error conditions
- **Database queries:** Always use `$wpdb->prepare()` for SQL queries

## Quality Checks

Run all checks before submitting a pull request:

```bash
# Static analysis (PHPStan level 5)
composer phpstan

# WordPress coding standards (PHP_CodeSniffer)
composer phpcs

# Unit and integration tests (PHPUnit)
composer test
```

All three checks must pass before a pull request can be merged.

## Pull Request Process

1. Fork the repository and create a feature branch from `main`
2. Make your changes, following the coding standards above
3. Add or update tests for any new or changed functionality
4. Run all quality checks (`composer phpstan`, `composer phpcs`, `composer test`)
5. Commit your changes using the conventional commit format (see below)
6. Submit a pull request against the `main` branch
7. Describe what your changes do and why in the PR description

## Commit Message Format

Use conventional commits:

```
type(scope): description
```

Types:

| Type | When to use |
|------|-------------|
| `feat` | New feature, endpoint, or component |
| `fix` | Bug fix or error correction |
| `docs` | Documentation-only changes |
| `test` | Adding or updating tests |
| `refactor` | Code restructuring with no behavior change |
| `chore` | Config, tooling, or dependency updates |

Examples:

```
feat(api): add bulk snippet export endpoint
fix(auth): handle expired application passwords gracefully
docs(readme): update installation instructions
test(snippets): add integration test for deploy endpoint
```

## Reporting Issues

Use [GitHub Issues](https://github.com/theduyet/ai-gateway/issues) to report bugs or request features. Include:

- WordPress version and PHP version
- Steps to reproduce the issue
- Expected vs actual behavior
- Relevant error messages or log output
