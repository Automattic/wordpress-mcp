This repository will be deprecated as the [mcp-adapter](https://github.com/wordpress/mcp-adapter) AI Building Block for WordPress continues releasing stable versions.  

The shift aligns with two important developments:
- The [Abilities API](https://github.com/WordPress/abilities-api) is moving into WordPress Core as of version 6.9.
- mcp-adapter is now stable and will become the canonical plugin and Composer package for MCP integration in WordPress.

We encourage all users to migrate to mcp-adapter. Future work, including new features and fixes, will happen there. This repository will remain available in archived form for historical reference.

# WordPress MCP

[![Latest Release](https://img.shields.io/github/v/release/Automattic/wordpress-mcp)](https://github.com/Automattic/wordpress-mcp/releases)

A comprehensive WordPress plugin that implements the [Model Context Protocol (MCP)](https://modelcontextprotocol.io) to expose WordPress functionality through standardized interfaces. This plugin enables AI models and applications to interact with WordPress sites securely using multiple transport protocols and enterprise-grade authentication.

## ✨ Features

-   🔄 **Dual Transport Protocols**: STDIO and HTTP-based (Streamable) transports
-   🔐 **JWT Authentication**: Secure token-based authentication with management UI
-   🎛️ **Admin Interface**: React-based token management and settings dashboard
-   🤖 **AI-Friendly APIs**: JSON-RPC 2.0 compliant endpoints for AI integration
-   🏗️ **Extensible Architecture**: Custom tools, resources, and prompts support
-   🔌 **WordPress Feature API**: Adapter for standardized WordPress functionality
-   🧪 **Experimental REST API CRUD Tools**: Generic tools for any WordPress REST API endpoint
-   🧪 **Comprehensive Testing**: 200+ test cases covering all protocols and authentication
-   ⚡ **High Performance**: Optimized routing and caching mechanisms
-   🔒 **Enterprise Security**: Multi-layer authentication and audit logging

## 🏗️ Architecture

The plugin implements a dual transport architecture:

```
WordPress MCP Plugin
├── Transport Layer
│   ├── McpStdioTransport (/wp/v2/wpmcp)
│   └── McpStreamableTransport (/wp/v2/wpmcp/streamable)
├── Authentication
│   └── JWT Authentication System
├── Method Handlers
│   ├── Tools, Resources, Prompts
│   └── System & Initialization
└── Admin Interface
    └── React-based Token Management
```

### Transport Protocols

| Protocol       | Endpoint                  | Format          | Authentication      | Use Case             |
| -------------- | ------------------------- | --------------- | ------------------- | -------------------- |
| **STDIO**      | `/wp/v2/wpmcp`            | WordPress-style | JWT + App Passwords | Legacy compatibility |
| **Streamable** | `/wp/v2/wpmcp/streamable` | JSON-RPC 2.0    | JWT only            | Modern AI clients    |

## 🚀 Installation

### Quick Install

1. Download `wordpress-mcp.zip` from [releases](https://github.com/Automattic/wordpress-mcp/releases/)
2. Upload to `/wp-content/plugins/wordpress-mcp` directory
3. Activate through WordPress admin 'Plugins' menu
4. Navigate to `Settings > WordPress MCP` to configure

### Composer Install (Development)

```bash
cd wp-content/plugins/
git clone https://github.com/Automattic/wordpress-mcp.git
cd wordpress-mcp
composer install --no-dev
npm install && npm run build
```

## 🔐 Authentication Setup

### JWT Token Generation

1. Go to `Settings > WordPress MCP > Authentication Tokens`
2. Select token duration (1-24 hours)
3. Click "Generate New Token"
4. Copy the token for use in your MCP client

### MCP Client Configuration

#### Claude Desktop Configuration using [mcp-wordpress-remote](https://github.com/Automattic/mcp-wordpress-remote) proxy

Add to your Claude Desktop `claude_desktop_config.json`:

```json
{
	"mcpServers": {
		"wordpress-mcp": {
			"command": "npx",
			"args": [ "-y", "@automattic/mcp-wordpress-remote@latest" ],
			"env": {
				"WP_API_URL": "https://your-site.com/",
				"JWT_TOKEN": "your-jwt-token-here",
				"LOG_FILE": "optional-path-to-log-file"
			}
		}
	}
}
```

#### Using Application Passwords (Alternative)

```json
{
	"mcpServers": {
		"wordpress-mcp": {
			"command": "npx",
			"args": [ "-y", "@automattic/mcp-wordpress-remote@latest" ],
			"env": {
				"WP_API_URL": "https://your-site.com/",
				"WP_API_USERNAME": "your-username",
				"WP_API_PASSWORD": "your-application-password",
				"LOG_FILE": "optional-path-to-log-file"
			}
		}
	}
}
```

#### VS Code MCP Extension (Direct Streamable Transport)

Add to your VS Code MCP settings:

```json
{
	"servers": {
		"wordpress-mcp": {
			"type": "http",
			"url": "https://your-site.com/wp-json/wp/v2/wpmcp/streamable",
			"headers": {
				"Authorization": "Bearer your-jwt-token-here"
			}
		}
	}
}
```

#### MCP Inspector (Development/Testing)

```bash
# Using JWT Token with proxy
npx @modelcontextprotocol/inspector \
  -e WP_API_URL=https://your-site.com/ \
  -e JWT_TOKEN=your-jwt-token-here \
  npx @automattic/mcp-wordpress-remote@latest

# Using Application Password with proxy
npx @modelcontextprotocol/inspector \
  -e WP_API_URL=https://your-site.com/ \
  -e WP_API_USERNAME=your-username \
  -e WP_API_PASSWORD=your-application-password \
  npx @automattic/mcp-wordpress-remote@latest
```

#### Local Development Configuration

```json
{
	"mcpServers": {
		"wordpress-local": {
			"command": "node",
			"args": [ "/path/to/mcp-wordpress-remote/dist/proxy.js" ],
			"env": {
				"WP_API_URL": "http://localhost:8080/",
				"JWT_TOKEN": "your-local-jwt-token",
				"LOG_FILE": "optional-path-to-log-file"
			}
		}
	}
}
```

## 🎯 Usage

### With MCP Clients

This plugin works seamlessly with MCP-compatible clients in two ways:

**Via Proxy:**

-   [mcp-wordpress-remote](https://github.com/Automattic/mcp-wordpress-remote) - Official MCP client with enhanced features
-   Claude Desktop with proxy configuration for full WordPress and WooCommerce support
-   Any MCP client using the STDIO transport protocol

**Direct Streamable Transport:**

-   VS Code MCP Extension connecting directly to `/wp/v2/wpmcp/streamable`
-   Custom HTTP-based MCP implementations using JSON-RPC 2.0
-   Any client supporting HTTP transport with JWT authentication

The streamable transport provides a direct JSON-RPC 2.0 compliant endpoint, while the proxy offers additional features like WooCommerce integration, enhanced logging, and compatibility with legacy authentication methods.

### Available MCP Methods

| Method           | Description              | Transport Support |
| ---------------- | ------------------------ | ----------------- |
| `initialize`     | Initialize MCP session   | Both              |
| `tools/list`     | List available tools     | Both              |
| `tools/call`     | Execute a tool           | Both              |
| `resources/list` | List available resources | Both              |
| `resources/read` | Read resource content    | Both              |
| `prompts/list`   | List available prompts   | Both              |
| `prompts/get`    | Get prompt template      | Both              |

### 🧪 Experimental REST API CRUD Tools

⚠️ **EXPERIMENTAL FEATURE**: This functionality is experimental and may change or be removed in future versions.

When enabled via `Settings > WordPress MCP > Enable REST API CRUD Tools`, the plugin provides three powerful generic tools that can interact with any WordPress REST API endpoint:

#### Available Tools

| Tool Name              | Description                                         | Type   |
| ---------------------- | --------------------------------------------------- | ------ |
| `list_api_functions`   | Discover all available WordPress REST API endpoints | Read   |
| `get_function_details` | Get detailed metadata for specific endpoint/method  | Read   |
| `run_api_function`     | Execute any REST API function with CRUD operations  | Action |

#### Usage Workflow

1. **Discovery**: Use `list_api_functions` to see all available endpoints
2. **Inspection**: Use `get_function_details` to understand required parameters
3. **Execution**: Use `run_api_function` to perform CRUD operations

#### Security & Permissions

-   **User Capabilities**: All operations respect current user permissions
-   **Settings Control**: Individual CRUD operations can be disabled in settings:
    -   Enable Create Tools (POST operations)
    -   Enable Update Tools (PATCH/PUT operations)
    -   Enable Delete Tools (DELETE operations)
-   **Automatic Filtering**: Excludes sensitive endpoints (JWT auth, oembed, autosaves, revisions)

#### Benefits

-   **Universal Access**: Works with any WordPress REST API endpoint, including custom post types and third-party plugins
-   **AI-Friendly**: Provides discovery and introspection capabilities for AI agents
-   **Standards Compliant**: Uses standard HTTP methods (GET, POST, PATCH, DELETE)
-   **Permission Safe**: Inherits WordPress user capabilities and respects endpoint permissions

## 🔧 Development

### Project Structure

```
wp-content/plugins/wordpress-mcp/
├── includes/                   # PHP classes
│   ├── Core/                  # Transport and core logic
│   ├── Auth/                  # JWT authentication
│   ├── Tools/                 # MCP tools
│   ├── Resources/             # MCP resources
│   ├── Prompts/               # MCP prompts
│   └── Admin/                 # Settings interface
├── src/                       # React components
│   └── settings/              # Admin UI components
├── tests/                     # Test suite
│   └── phpunit/              # PHPUnit tests
└── docs/                      # Documentation
```

### Adding Custom Tools

You can extend the MCP functionality by adding custom tools through your own plugins or themes. Create a new tool class in your plugin or theme:

```php
<?php
declare(strict_types=1);

use Automattic\WordpressMcp\Core\RegisterMcpTool;

class MyCustomTool {
    public function __construct() {
        add_action('wordpress_mcp_init', [$this, 'register_tools']);
    }

    public function register_tools(): void {
        new RegisterMcpTool([
            'name' => 'my_custom_tool',
            'description' => 'My custom tool description',
            'type' => 'read', // or 'action' for tools that modify data
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'param1' => ['type' => 'string', 'description' => 'Parameter 1']
                ],
                'required' => ['param1']
            ],
            'callback' => [$this, 'execute'],
            'permission_callback' => [$this, 'check_permissions'], // optional
        ]);
    }

    public function execute(array $args): array {
        // Your tool logic here
        return ['result' => 'success'];
    }

    public function check_permissions(): bool {
        // Optional: Add custom permission checks
        return current_user_can('edit_posts');
    }
}
```

### Adding Custom Resources

You can extend the MCP functionality by adding custom resources through your own plugins or themes. Create a new resource class in your plugin or theme:

```php
<?php
declare(strict_types=1);

use Automattic\WordpressMcp\Core\RegisterMcpResource;

class MyCustomResource {
    public function __construct() {
        add_action('wordpress_mcp_init', [$this, 'register_resources']);
    }

    public function register_resources(): void {
        new RegisterMcpResource([
            'uri' => 'custom://my-resource',
            'name' => 'My Custom Resource',
            'description' => 'Custom resource description',
            'mimeType' => 'application/json',
            'callback' => [$this, 'get_content'],
        ]);
    }

    public function get_content(): array {
        return ['contents' => [/* resource data */]];
    }
}
```

### Testing

Run the comprehensive test suite:

```bash
# Run all tests
vendor/bin/phpunit

# Run specific test suites
vendor/bin/phpunit tests/phpunit/McpStdioTransportTest.php
vendor/bin/phpunit tests/phpunit/McpStreamableTransportTest.php
vendor/bin/phpunit tests/phpunit/JwtAuthTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Building Frontend

```bash
# Development build
npm run dev

# Production build
npm run build

# Watch mode
npm run start
```

## 🔒 Security

### Best Practices

-   **Token Management**: Use shortest expiration time needed (1-24 hours)
-   **User Permissions**: Tokens inherit user capabilities
-   **Secure Storage**: Never commit tokens to repositories
-   **Regular Cleanup**: Revoke unused tokens promptly
-   **Access Control**: Streamable transport requires admin privileges
-   **CRUD Operations**: Only enable create/update/delete tools when necessary
-   **Experimental Features**: Use REST API CRUD tools with caution in production environments

### Security Features

-   ✅ JWT signature validation
-   ✅ Token expiration and revocation
-   ✅ User capability inheritance
-   ✅ Secure secret key generation
-   ✅ Audit logging for security events
-   ✅ Protection against malformed requests

## 📊 Testing Coverage

The plugin includes extensive testing:

-   **Transport Testing**: Both STDIO and Streamable protocols
-   **Authentication Testing**: JWT generation, validation, and revocation
-   **Integration Testing**: Cross-transport comparison
-   **Security Testing**: Edge cases and malformed requests
-   **Performance Testing**: Load and stress testing

View detailed testing documentation in [`tests/README.md`](tests/README.md).

## 🔧 Configuration

### Environment Variables

```php
// wp-config.php
define('WPMCP_JWT_SECRET_KEY', 'your-secret-key');
define('WPMCP_DEBUG', true); // Enable debug logging
```

### Plugin Settings

Access via `Settings > WordPress MCP`:

-   **Enable/Disable MCP**: Toggle plugin functionality
-   **Transport Configuration**: Configure STDIO/Streamable transports
-   **Feature Toggles**: Enable/disable specific tools and resources
-   **CRUD Operation Controls**: Granular control over create, update, and delete operations
-   **Experimental Features**: Enable REST API CRUD Tools (experimental functionality)
-   **Authentication Settings**: JWT token management

#### CRUD Operation Settings

The plugin provides granular control over CRUD operations:

-   **Enable Create Tools**: Allow POST operations via MCP tools
-   **Enable Update Tools**: Allow PATCH/PUT operations via MCP tools
-   **Enable Delete Tools**: ⚠️ Allow DELETE operations via MCP tools (use with caution)
-   **Enable REST API CRUD Tools**: 🧪 Enable experimental generic REST API access tools

⚠️ **Security Note**: Delete operations can permanently remove data. Only enable delete tools if you trust all users with MCP access.

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md).

### Development Setup

1. Clone the repository
2. Run `composer install` for PHP dependencies
3. Run `npm install` for JavaScript dependencies
4. Set up WordPress test environment
5. Run tests with `vendor/bin/phpunit`

## 📚 Documentation

-   **Documentation Overview**: [docs/README.md](docs/README.md)
-   **Client Setup Guide**: [docs/client-setup.md](docs/client-setup.md)
-   **AI Integration Guide**: [docs/for-ai.md](docs/for-ai.md)
-   **Registered Tools**: [docs/registered-tools.md](docs/registered-tools.md)
-   **Registered Resources**: [docs/registered-resources.md](docs/registered-resources.md)
-   **Registered Prompts**: [docs/registered-prompts.md](docs/registered-prompts.md)
-   **Register MCP Tools**: [docs/register-mcp-tools.md](docs/register-mcp-tools.md)
-   **Register MCP Prompts**: [docs/register-mcp-prompt.md](docs/register-mcp-prompt.md)
-   **Register MCP Resources**: [docs/register-mcp-resources.md](docs/register-mcp-resources.md)
-   **Testing Guide**: [tests/README.md](tests/README.md)

## 🆘 Support

For support and questions:

-   📖 **Documentation**: [docs/README.md](docs/README.md)
-   🐛 **Bug Reports**: [GitHub Issues](https://github.com/Automattic/wordpress-mcp/issues)
-   💬 **Discussions**: [GitHub Discussions](https://github.com/Automattic/wordpress-mcp/discussions)
-   ✉️ **Contact**: Reach out to the maintainers

## 📄 License

This project is licensed under the [GPL v2 or later](LICENSE).

---

Built with ❤️ by [Automattic](https://automattic.com) for the WordPress and AI communities.
