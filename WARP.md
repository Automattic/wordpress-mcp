# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Common Development Commands

### Build & Development
```bash
# Production build
npm run build

# Development build with watch mode
npm run start

# Clean build directory
npm run clean
```

### Local Development with wp-env
```bash
# Start WordPress development environment
wp-env start

# Stop WordPress development environment
wp-env stop

# Reset the environment
wp-env destroy
wp-env start

# Run WP-CLI commands in wp-env
wp-env run cli wp mcp validate-tools

# Access the development site
# Frontend: http://localhost:8888
# Admin: http://localhost:8888/wp-admin (admin/password)
```

### Testing
```bash
# Run all PHPUnit tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/phpunit/McpStdioTransportTest.php

# Run specific test suite
vendor/bin/phpunit --testsuite wordpress-mcp

# Run with code coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Linting & Formatting
```bash
# PHP linting (WordPress Coding Standards)
npm run lint:php
npm run lint:php:fix

# JavaScript linting
npm run lint:js

# CSS linting  
npm run lint:css

# Format JavaScript
npm run format
```

### Package Management
```bash
# Install all dependencies (PHP + JS)
composer install
npm install

# Production dependencies only
composer install --no-dev

# Update WordPress packages
npm run packages-update
```

### Plugin Distribution
```bash
# Create plugin ZIP file
npm run plugin-zip

# Build and create production-ready ZIP
npm run plugin-zip:build
```

### WP-CLI Commands
```bash
# Validate all registered MCP tools
wp mcp validate-tools
```

## High-Level Architecture

### Core Components

**Transport Layer** - Dual protocol support with shared base class:
- `McpTransportBase` - Abstract base containing shared handler logic and routing
- `McpStdioTransport` - WordPress-style REST endpoint at `/wp/v2/wpmcp`
- `McpStreamableTransport` - JSON-RPC 2.0 compliant endpoint at `/wp/v2/wpmcp/streamable`

**Request Handler Pipeline**:
- `InitializeHandler` - MCP session initialization and capability negotiation
- `ToolsHandler` - Tool registration, listing, and execution
- `ResourcesHandler` - Resource discovery, reading, and subscription management
- `PromptsHandler` - Prompt template management and rendering
- `SystemHandler` - System methods (ping, logging, completion)

**Authentication**:
- `JwtAuth` - JWT token generation, validation, and management
- Token storage in WordPress options with expiration tracking
- Bearer token authentication for Streamable transport
- Application password support for STDIO transport

**Registration System**:
- `RegisterMcpTool` - Tool registration with JSON schema validation
- `RegisterMcpResource` - Resource registration with URI patterns
- `RegisterMcpPrompt` - Prompt template registration
- Hook-based extensibility via `wp_mcp_register_*` actions

**WordPress Integration**:
- `WpFeaturesAdapter` - Adapts WordPress Feature API to MCP protocol
- `McpRestApiCrud` - Experimental generic REST API tool generator
- Custom post type tools (Posts, Pages, Media)
- Settings and site info resources

**Admin Interface**:
- React-based settings panel at `Settings > WordPress MCP`
- JWT token management UI
- Feature toggles for experimental functionality
- Built with `@wordpress/scripts` build pipeline

### Request Flow

1. **Request arrives** at transport endpoint (STDIO or Streamable)
2. **Authentication** via JWT token validation (or app password for STDIO)
3. **Route matching** in `McpTransportBase::route_request()` 
4. **Handler execution** with appropriate method handler class
5. **Response formatting** based on transport protocol (REST or JSON-RPC)
6. **Error handling** with protocol-specific error formats

### Extension Points

**Adding Custom Tools**:
```php
add_action('wp_mcp_register_tools', function() {
    WPMCP()->register_tool([
        'name' => 'my_tool',
        'description' => 'Tool description',
        'inputSchema' => [...],
        'callback' => [$this, 'execute']
    ]);
});
```

**Adding Custom Resources**:
```php
add_action('wp_mcp_register_resources', function() {
    WPMCP()->register_resource([
        'uri' => 'custom://resource',
        'name' => 'My Resource',
        'mimeType' => 'application/json',
        'callback' => [$this, 'get_content']
    ]);
});
```

### Key Directories

- `includes/Core/` - Transport implementations and core MCP logic
- `includes/Auth/` - JWT authentication system
- `includes/Tools/` - Built-in MCP tools
- `includes/Resources/` - Built-in MCP resources  
- `includes/Prompts/` - Built-in MCP prompts
- `includes/RequestMethodHandlers/` - MCP method handlers
- `includes/Admin/` - WordPress admin interface
- `src/settings/` - React components for admin UI
- `tests/phpunit/` - PHPUnit test suite

### Testing Strategy

The plugin includes comprehensive test coverage:
- Transport protocol tests comparing STDIO vs Streamable responses
- JWT authentication lifecycle tests
- Tool registration and execution tests
- Integration tests for cross-transport consistency
- Mock WordPress functions for isolated testing

### Security Considerations

- JWT tokens inherit user capabilities
- Tokens expire after configured duration (1-24 hours)
- CRUD operations require explicit admin enablement
- Sensitive endpoints filtered from REST API discovery
- All operations respect WordPress user permissions

## Important Notes

- **Deprecation Notice**: This plugin is being deprecated in favor of [mcp-adapter](https://github.com/wordpress/mcp-adapter)
- The [Abilities API](https://github.com/WordPress/abilities-api) is moving into WordPress Core as of version 6.9
- REST API CRUD tools are experimental and may change
- Requires PHP 8.0+ and WordPress 6.4+
- Uses Firebase JWT library for token handling
- Depends on wordpress/mcp-adapter via Composer

### Migration Path
When using wp-env, the `.wp-env.json` typically includes:
```json
{
  "plugins": [
    "WordPress/abilities-api",
    "WordPress/mcp-adapter"
  ]
}
```
These are the replacement components for this plugin.

## Related Documentation

- [Client Setup Guide](docs/client-setup.md) - MCP client configuration
- [Troubleshooting](docs/troubleshooting.md) - Common issues and solutions
- [WP-CLI Commands](docs/wp-cli-commands.md) - Available CLI commands
- [Testing README](tests/README.md) - Detailed testing documentation
