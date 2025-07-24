# PHP Linting Setup

This plugin uses PHP_CodeSniffer (PHPCS) with WordPress Coding Standards for PHP code quality and consistency.

## Available Commands

### Check for violations
```bash
pnpm run lint:php
```

### Auto-fix violations
```bash
pnpm run lint:php:fix
```

## Configuration

The PHP linting is configured via `.phpcs.xml.dist` in the plugin root directory. The configuration:

- Uses WordPress Coding Standards
- Ignores vendor, build, docs, node_modules, tests, and _ovi directories
- Sets PHP version compatibility to 8.0+
- Configures proper text domain (`wordpress-mcp`) and function prefixes (`wordpress_mcp`, `WPMCP`)

## Standards Applied

- **WordPress Coding Standards**: Ensures code follows WordPress conventions
- **PSR2**: Basic PHP coding standards
- **Squiz**: Additional code quality checks
- **Generic**: General PHP best practices

## Common Issues and Fixes

### Namespace Prefixing
All namespaces should start with the plugin prefix. Currently using `Automattic\WordpressMcp` but should be updated to follow WordPress naming conventions.

### Text Domain
All internationalization functions should use the `wordpress-mcp` text domain.

### Function Prefixing
Global functions should be prefixed with `wordpress_mcp_` or `WPMCP`.

## Auto-fixable Issues

The `lint:php:fix` command can automatically fix many common issues:
- Whitespace and formatting
- Array alignment
- Control structure spacing
- Function call spacing
- Missing newlines

## Manual Fixes Required

Some issues require manual fixes:
- Namespace prefixing
- Missing doc comments
- Unused parameters
- Direct database queries
- Security issues

## Integration with CI/CD

The linting can be integrated into your CI/CD pipeline by running:
```bash
pnpm run lint:php
```

The command will exit with code 2 if violations are found, making it suitable for automated checks. 