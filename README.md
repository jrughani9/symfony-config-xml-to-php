# Symfony Config XML to PHP Converter

A tool to convert Symfony XML configuration files to PHP format. Since XML configuration format is being removed from Symfony 8.0, this tool helps automate the migration of your XML config files to PHP.

**⚠️ This script is a best-effort tool and does not guarantee a perfect conversion. Manual review and adjustments may be necessary.**

## Installation
clone the fork and add the repository as a composer repository:
```bash
composer config repositories.symfony-xml-converter path {path to this repository}
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


## Contributing

Feel free to open issues or submit pull requests for improvements or bug fixes.

## License

MIT License. See the LICENSE file for details.
