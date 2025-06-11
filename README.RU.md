# bermudaphp/tokenizer

[🇺🇸 English](README.md) | 🇷🇺 Русский

Оптимизированный PHP токенизатор для поиска объявлений классов, интерфейсов, трейтов и енумов.

## Особенности

- **Полная поддержка PHP 8+** - работает с классами, интерфейсами, трейтами и енумами
- **Поддержка модификаторов** - abstract, final, readonly классы
- **Игнорирование анонимных классов** - фокус на именованных объявлениях
- **Поддержка атрибутов PHP** - корректная обработка `#[Attribute]`
- **Виртуальные свойства** - удобный доступ к информации о классах
- **Настраиваемый поиск** - битовые флаги для выборочного поиска

## Установка

```bash
composer require bermudaphp/tokenizer
```

## Быстрый старт

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

// Найти все объявления
$declarations = $tokenizer->parse($source);

foreach ($declarations as $classInfo) {
    echo "Найден {$classInfo->type}: {$classInfo->fullQualifiedName}\n";
    echo "Пространство имен: {$classInfo->namespace}\n";
    echo "Имя класса: {$classInfo->name}\n";
    echo "Абстрактный: " . ($classInfo->isAbstract ? 'да' : 'нет') . "\n";
    echo "---\n";
}
```

## Режимы поиска

Используйте битовые флаги для настройки типов объявлений для поиска:

```php
// Искать только классы
$classes = $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES);

// Искать только интерфейсы и трейты
$interfaces = $tokenizer->parse($source, 
    TokenizerInterface::SEARCH_INTERFACES | TokenizerInterface::SEARCH_TRAITS
);

// Искать всё (по умолчанию)
$all = $tokenizer->parse($source, TokenizerInterface::SEARCH_ALL);
```

### Доступные флаги

| Константа | Значение | Описание |
|-----------|----------|----------|
| `SEARCH_CLASSES` | 1 | Искать классы |
| `SEARCH_INTERFACES` | 2 | Искать интерфейсы |
| `SEARCH_TRAITS` | 4 | Искать трейты |
| `SEARCH_ENUMS` | 8 | Искать енумы |
| `SEARCH_ALL` | 15 | Искать всё |

## ClassInfo API

Класс `ClassInfo` предоставляет подробную информацию о найденных объявлениях:

### Свойства

```php
$classInfo->fullQualifiedName;  // string: Полное квалифицированное имя
$classInfo->type;              // string: 'class'|'interface'|'trait'|'enum'
$classInfo->isAbstract;        // bool: Абстрактный класс
$classInfo->isFinal;           // bool: Финальный класс
$classInfo->isReadonly;        // bool: Readonly класс
```

### Виртуальные свойства

```php
$classInfo->name;              // string: Имя без пространства имен
$classInfo->namespace;         // string: Пространство имен без имени класса
$classInfo->isClass;          // bool: Это класс?
$classInfo->isInterface;      // bool: Это интерфейс?
$classInfo->isTrait;          // bool: Это трейт?
$classInfo->isEnum;           // bool: Это енум?
$classInfo->isConcrete;       // bool: Конкретный класс (не абстрактный)?
$classInfo->reflector;        // ReflectionClass|ReflectionEnum: Рефлектор
```

### Методы

```php
$classInfo->exists();         // bool: Существует ли класс в памяти
```

## Примеры использования

### Поиск конкретных классов

```php
$source = file_get_contents('path/to/file.php');
$declarations = $tokenizer->parse($source, TokenizerInterface::SEARCH_CLASSES);

$concreteClasses = array_filter($declarations, fn($info) => $info->isConcrete);
```

### Работа с рефлекцией

```php
foreach ($declarations as $classInfo) {
    if ($classInfo->exists()) {
        $reflection = $classInfo->reflector;
        echo "Класс {$classInfo->name} имеет " . count($reflection->getMethods()) . " методов\n";
    }
}
```

### Группировка по пространствам имен

```php
$byNamespace = [];
foreach ($declarations as $classInfo) {
    $byNamespace[$classInfo->namespace][] = $classInfo->name;
}
```

## Требования

- PHP 8.4 или выше
- Расширение `tokenizer` (обычно входит в стандартную сборку PHP)

## Лицензия

MIT License. Подробности в файле [LICENSE](LICENSE).
