# MCP Tool Validation Schemas

This directory contains JSON Schema definitions for validating MCP tools in the WordPress MCP plugin.

## Schema Files

### `mcp-2025-06-18.json`
- **Source**: [MCP Official Schema](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/main/schema/2025-06-18/schema.json)
- **Purpose**: Official Model Context Protocol specification schema
- **Usage**: Validates core MCP tool compliance
- **Update**: Manual updates when MCP specification changes

### `wordpress-mcp-extensions.json`
- **Source**: WordPress MCP Plugin (custom)
- **Purpose**: WordPress-specific extensions to MCP tools
- **Usage**: Validates WordPress-specific fields like `type`, `rest_alias`, etc.
- **Maintenance**: Updated as WordPress MCP features evolve

## Validation Levels

### Strict MCP (`validate_mcp_strict`)
- Validates only against `mcp-2025-06-18.json`
- Ensures pure MCP specification compliance
- Ignores WordPress-specific fields
- Use when: Preparing tools for other MCP implementations

### WordPress Extended (`validate_wordpress_extended`) - **Default**
- Validates against both schemas
- Allows WordPress-specific fields
- Permits additional custom fields
- Use when: Standard WordPress MCP tool validation

### Permissive (`validate_permissive`)
- Same as Extended but reports errors as warnings
- Use when: Development, debugging, migration

## Usage in Code

```php
use Automattic\WordpressMcp\Utils\SchemaValidator;

// Load schemas
$mcp_schema = SchemaValidator::load_schema('mcp-2025-06-18');
$wp_schema = SchemaValidator::load_schema('wordpress-mcp-extensions');

// Validate tool
$result = SchemaValidator::validate_against_schema($tool, $mcp_schema);
```

## Schema Caching

Schemas are cached in memory for performance. Cache is invalidated when:
- Plugin updates
- Schema files are modified
- Cache TTL expires (default: 1 hour)

## Updating Schemas

### MCP Schema Updates
```bash
# Download latest MCP schema
curl -o includes/schemas/mcp-2025-06-18.json \
  https://raw.githubusercontent.com/modelcontextprotocol/modelcontextprotocol/main/schema/2025-06-18/schema.json
```

### WordPress Extensions
- Edit `wordpress-mcp-extensions.json` directly
- Validate JSON syntax before committing
- Update version in plugin when schema changes
