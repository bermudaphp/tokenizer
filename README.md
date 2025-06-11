# bermudaphp/tokenizer

🇺🇸 English | [🇷🇺 Русский](README.md)

PHP tokenizer for finding class, interface, trait, and enum declarations.

## Features

- **High Performance** - optimized PHP token parsing
- **Full PHP 8+ Support** - works with classes, interfaces, traits, and enums
- **Modifier Support** - handles abstract, final, readonly classes
- **Anonymous Class Filtering** - focuses on named declarations only
- **PHP Attributes Support** - properly handles `#[Attribute]` syntax
- **Configurable Search** - bitwise flags for selective searching

## Installation

```bash
composer require bermudaphp/tokenizer
```

## Quick Start

```php
<?php

use Bermuda\Tokenizer\Tokenizer;
use Bermuda\Tokenizer\TokenizerInterface;

$tokenizer = new Tokenizer();
$source = '<?php 
namespace App\Models;

abstract class User {
    // ...
}

interface UserInterface {
    // ...
}
';

// Find all declarations
$declarations = $tokenizer->parse($source);

foreach ($declarations as $classInfo) {
    echo "Found {$classInfo->type}: {$classInfo->fullQualifiedName}\n";
    echo "Namespace: {$classInfo->namespace}\n";
    echo "Class name: {$classInfo->name}\n";
    echo "Abstract: " . ($classInfo->isAbstract ? 'yes' : 'no') . "\n";
    echo "---\n";
}
```

## Search Modes

Use bitwise flags to configure which declaration types to search for:

```php
// Search only classes
$classes = $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES);

// Search only interfaces and traits
$interfaces = $tokenizer->parse($source, 
    TokenizerInterface::SEARCH_INTERFACES | TokenizerInterface::SEARCH_TRAITS
);

// Search everything (default)
$all = $tokenizer->parse($source, TokenizerInterface::SEARCH_ALL);
```

### Available Flags

| Constant | Value | Description |
|----------|-------|-------------|
| `SEARCH_CLASSES` | 1 | Search for classes |
| `SEARCH_INTERFACES` | 2 | Search for interfaces |
| `SEARCH_TRAITS` | 4 | Search for traits |
| `SEARCH_ENUMS` | 8 | Search for enums |
| `SEARCH_ALL` | 15 | Search for everything |

## ClassInfo API

The `ClassInfo` class provides detailed information about found declarations:

### Properties

```php
$classInfo->fullQualifiedName;  // string: Fully qualified class name
$classInfo->type;              // string: 'class'|'interface'|'trait'|'enum'
$classInfo->isAbstract;        // bool: Is abstract class
$classInfo->isFinal;           // bool: Is final class
$classInfo->isReadonly;        // bool: Is readonly class
```

### Virtual Properties

```php
$classInfo->name;              // string: Class name without namespace
$classInfo->namespace;         // string: Namespace without class name
$classInfo->isClass;          // bool: Is this a class?
$classInfo->isInterface;      // bool: Is this an interface?
$classInfo->isTrait;          // bool: Is this a trait?
$classInfo->isEnum;           // bool: Is this an enum?
$classInfo->isConcrete;       // bool: Is concrete class (not abstract)?
$classInfo->reflector;        // ReflectionClass|ReflectionEnum: Reflector instance
```

### Methods

```php
$classInfo->exists();         // bool: Does the class exist in memory
```

## Usage Examples

### Finding Concrete Classes

```php
$source = file_get_contents('path/to/file.php');
$declarations = $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES);

$concreteClasses = array_filter($declarations, fn($info) => $info->isConcrete);
```

### Working with Reflection

```php
foreach ($declarations as $classInfo) {
    if ($classInfo->exists()) {
        $reflection = $classInfo->reflector;
        echo "Class {$classInfo->name} has " . count($reflection->getMethods()) . " methods\n";
    }
}
```

### Grouping by Namespace

```php
$byNamespace = [];
foreach ($declarations as $classInfo) {
    $byNamespace[$classInfo->namespace][] = $classInfo->name;
}
```

## Requirements

- PHP 8.1 or higher
- `tokenizer` extension (usually included in standard PHP builds)

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Contributing

We welcome contributions! Please see our contributing guidelines for more information.

## Support

If you have questions or issues, please create an issue in the GitHub repository.
