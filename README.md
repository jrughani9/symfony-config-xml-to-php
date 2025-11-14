# Symfony Config XML to PHP Converter

A tool to convert Symfony XML configuration files to PHP format. Since XML configuration format is being removed from Symfony 8.0, this tool helps automate the migration of your XML config files to PHP.

**⚠️ This script is a best-effort tool and does not guarantee a perfect conversion. Manual review and adjustments may be necessary.**

## Features

### Supported Configuration Types
- **Service Container Configuration** (`services.xml`)
- **Routing Configuration** (`routes.xml`)

### Service Container Features
- Service definitions with arguments, calls, properties, and tags
- Service aliases and abstract services
- Autowiring and autoconfiguration
- Factory services (static, service-based, and expression-based)
- Service decoration
- Tagged services and tagged iterators
- Service locators
- Service bindings
- Prototype definitions with namespace loading
- Environment-specific configurations (`when` blocks)
- Imports and parameters

### Routing Features
- Route definitions with paths, controllers, methods
- Route requirements, defaults, and options
- Route conditions
- Route imports with prefixes
- Host and scheme configurations
- Environment-specific routing

## Installation

```bash
composer require --dev gromnan/symfony-config-xml-to-php
```

## Usage

### Convert a single file:
```bash
vendor/bin/convert path/to/services.xml
```

### Convert all XML files in a directory:
```bash
vendor/bin/convert path/to/config/
```

### Convert to a specific output directory:
```bash
vendor/bin/convert path/to/config/ output/directory/
```

### Options

- `--dry-run`: Preview the changes without writing files
- `--overwrite`: Overwrite existing PHP files
- `--skip-validation`: Skip XML validation for faster processing
- `--exclude`: Exclude patterns (can be used multiple times)

### Examples

```bash
# Preview conversion without writing files
vendor/bin/convert config/services/ --dry-run

# Convert and overwrite existing PHP files
vendor/bin/convert config/ --overwrite

# Convert with exclusions
vendor/bin/convert config/ --exclude="test/*" --exclude="dev/*"
```

## Known Issues and Limitations

1. **Manual Review Required**: Complex configurations may need manual adjustments after conversion
2. **Limited Scope**: Only supports service container and routing configurations (not security, doctrine, etc.)
3. **Special Characters**: The converter properly escapes backslashes in namespaces and class names
4. **Comments**: XML comments are preserved as PHP comments where possible

## Recent Updates

- Fixed namespace escaping issue for prototype/load declarations with trailing backslashes
- Improved handling of service IDs and class names containing backslashes
- Enhanced error reporting and progress tracking

## Contributing

Feel free to open issues or submit pull requests for improvements or bug fixes.

## License

MIT License. See the LICENSE file for details.
