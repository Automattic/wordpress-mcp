# WordPress MCP WP-CLI Commands

This document describes the WP-CLI commands available for the WordPress MCP plugin.

## wp mcp validate-tools

Validates all registered MCP tools or a specific tool by name using the enhanced `ValidateTool` utility class with multiple validation levels.

### Synopsis

```
wp mcp validate-tools [<tool_name>] [--level=<level>] [--format=<format>] [--errors-only] [--warnings-only] [--detailed]
```

### Options

**`[<tool_name>]`**
The name of a specific tool to validate. If not provided, all tools will be validated.

**`[--level=<level>]`**
Validation level to use. Default: `extended`
- `strict` - Validates only against official MCP specification (ignores WordPress-specific fields)
- `extended` - Validates MCP compliance + WordPress extensions (default)
- `permissive` - Same as extended but reports errors as warnings

**`[--format=<format>]`**
Output format. Default: `table`
- `table` - Human-readable table format
- `json` - JSON output
- `yaml` - YAML output  
- `csv` - CSV output

**`[--errors-only]`**
Show only tools with validation errors.

**`[--warnings-only]`**
Show only tools with validation warnings.

**`[--detailed]`**
Show detailed validation messages for each tool.

### Examples

#### Basic Usage

```bash
# Validate all tools with default (extended) level
wp mcp validate-tools

# Validate all tools and show detailed information
wp mcp validate-tools --detailed

# Validate a specific tool
wp mcp validate-tools create_post
```

#### Validation Levels

```bash
# Strict MCP compliance only (ignores WordPress-specific fields)
wp mcp validate-tools --level=strict

# Extended validation (MCP + WordPress fields) - Default
wp mcp validate-tools --level=extended

# Permissive mode (warnings instead of errors)
wp mcp validate-tools --level=permissive --detailed
```

#### Filtering and Formatting

```bash
# Show only tools with errors in JSON format
wp mcp validate-tools --errors-only --format=json

# Show only tools with warnings
wp mcp validate-tools --warnings-only

# Get summary as CSV
wp mcp validate-tools --format=csv
```

#### Specific Tool Validation

```bash
# Validate specific tool with strict MCP compliance
wp mcp validate-tools create_post --level=strict

# Check a tool in permissive mode with details
wp mcp validate-tools update_post --level=permissive --detailed
```

### Output

#### Table Format (Default)

The command displays a validation summary followed by a table with the following columns:

- **Status**: ✓ (valid) or ✗ (invalid)
- **Name**: Tool name
- **Type**: WordPress tool type (create, read, update, delete, action)
- **Enabled**: Whether the tool is enabled
- **REST**: Whether the tool has REST alias
- **Errors**: Number of validation errors
- **Warnings**: Number of validation warnings

Example output:
```
Validating 15 tools with extended level...

Validation Summary (Level: extended)
Total Tools: 15
Valid: 12
Invalid: 3
Valid with Warnings: 2
Success Rate: 80.0%

+--------+------------------+--------+---------+------+--------+----------+
| Status | Name             | Type   | Enabled | REST | Errors | Warnings |
+--------+------------------+--------+---------+------+--------+----------+
| ✓      | create_post      | create | Yes     | Yes  | 0      | 1        |
| ✗      | invalid_tool     | read   | Yes     | No   | 2      | 0        |
| ✓      | update_post      | update | Yes     | Yes  | 0      | 0        |
+--------+------------------+--------+---------+------+--------+----------+
```

#### Detailed Output

When using `--detailed`, the command shows specific error and warning messages:

```
Detailed Validation Results

create_post
  Warnings:
    - Tool is disabled because REST API CRUD tools are enabled.

invalid_tool
  Errors:
    - Tool description is required.
    - Input schema must be an object type.
```

#### Single Tool Output

When validating a specific tool:

```
Tool: create_post
Status: Valid
Type: create
Validation Level: extended
Enabled: Yes
Has REST Alias: Yes

Warnings:
  - Tool is disabled because REST API CRUD tools are enabled.
```

### Validation Levels Explained

#### Strict Level (`--level=strict`)
- Validates only against the official MCP specification
- Ignores WordPress-specific fields (`type`, `rest_alias`, etc.)
- Use when: Preparing tools for other MCP implementations
- Strictest compliance checking

#### Extended Level (`--level=extended`) - **Default**
- Validates MCP compliance + WordPress extensions
- Checks all MCP fields: `name`, `description`, `title`, `inputSchema`, `outputSchema`, `annotations`
- Validates WordPress-specific fields: `type`, `rest_alias`, enablement flags
- Allows additional custom fields
- Use when: Standard WordPress MCP tool validation

#### Permissive Level (`--level=permissive`)
- Same validation rules as Extended
- Reports validation issues as warnings instead of errors
- Never fails with exit code 1
- Use when: Development, debugging, migration scenarios

### Enhanced MCP Field Validation

The enhanced validator now checks these MCP specification fields:

#### Core MCP Fields
- **`name`** (required): 1-64 characters, alphanumeric + underscore/hyphen
- **`description`** (required): Tool description text
- **`title`** (optional): Under 200 characters
- **`inputSchema`** (optional): JSON Schema for tool inputs
- **`outputSchema`** (optional): JSON Schema for tool outputs  
- **`annotations`** (optional): Metadata with audience, priority, lastModified

#### JSON Schema Validation
Comprehensive validation of `inputSchema` and `outputSchema`:
- Type constraints (string, number, integer, boolean, array, object, null)
- String constraints (minLength, maxLength, pattern)
- Numeric constraints (minimum, maximum, multipleOf)
- Array constraints (minItems, maxItems, items)
- Object constraints (properties, required, additionalProperties)

#### Annotations Validation
- **`audience`**: Array containing 'user' and/or 'assistant'
- **`priority`**: Number between 0 and 1
- **`lastModified`**: Valid ISO 8601 timestamp

### Exit Codes

- **0**: All validated tools are valid
- **1**: One or more tools have validation errors (except in permissive mode)

### Prerequisites

- WordPress MCP plugin must be active
- WP-CLI must be installed and available
- Tools must be registered via `rest_api_init` action

### Error Handling

The command handles various error scenarios:

- **Plugin not active**: Exits with error message
- **Tool not found**: Exits with specific tool name in error
- **Invalid validation level**: Lists valid options
- **No tools available**: Shows warning message
- **Schema validation issues**: Reports as warnings, continues validation

### Troubleshooting

#### "WordPress MCP plugin is not active or properly configured"
- Ensure the plugin is activated
- Check that the WpMcp class is loaded

#### "Tool 'toolname' not found"
- Run `wp mcp validate-tools` to see all available tools
- Check tool name spelling and case sensitivity

#### Schema validation warnings
- Schema validation failures are reported as warnings
- Does not affect tool validation result
- Usually indicates missing schema files or JSON decode errors

### Programmatic Usage

The validation functionality is also available as a utility class for use in other parts of the plugin:

```php
use Automattic\WordpressMcp\Utils\ValidateTool;
use Automattic\WordpressMcp\Utils\SchemaValidator;

// Validate single tool with different levels
$tool = ['name' => 'my_tool', 'description' => 'My custom tool'];

$strict_result = ValidateTool::validate_mcp_strict($tool);
$extended_result = ValidateTool::validate_wordpress_extended($tool);
$permissive_result = ValidateTool::validate_permissive($tool);

// Validate multiple tools
$tools = [$tool1, $tool2, $tool3];
$results = ValidateTool::validate_tools($tools, ValidateTool::LEVEL_EXTENDED);

// Get validation summary
$summary = ValidateTool::get_validation_summary($results);
echo "Success rate: {$summary['success_rate']}%";

// Filter results
$errors_only = ValidateTool::filter_errors_only($results);
$warnings_only = ValidateTool::filter_warnings_only($results);

// Check individual results
if (ValidateTool::has_errors($result)) {
    $errors = ValidateTool::get_errors($result);
    foreach ($errors as $error) {
        error_log("Tool validation error: $error");
    }
}

// Schema validation
$mcp_validation = SchemaValidator::validate_mcp_tool($tool);
$wp_validation = SchemaValidator::validate_wordpress_tool($tool);
```

### Architecture

The command uses a multi-layered validation architecture:

#### ValidateTool Utility Class
- **`validate_mcp_strict()`** - MCP specification only
- **`validate_wordpress_extended()`** - MCP + WordPress fields  
- **`validate_permissive()`** - Non-breaking validation
- **`validate_tools()`** - Batch validation with level support

#### SchemaValidator Utility Class
- **JSON Schema loading and caching**
- **Comprehensive schema validation**
- **MCP specification compliance checking**
- **WordPress extension validation**

#### Validation Levels
- **Static constants** for validation levels
- **Flexible error/warning reporting** based on level
- **Schema integration** when available

This separation allows the validation logic to be used in other contexts beyond just the WP-CLI command, such as:
- Admin interfaces for tool management
- Automated testing and quality assurance
- Plugin activation/update hooks
- Developer debugging and troubleshooting 