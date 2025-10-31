# Wellspring

[![PHP from Packagist](https://img.shields.io/packagist/php-v/decodelabs/wellspring?style=flat)](https://packagist.org/packages/decodelabs/wellspring)
[![Latest Version](https://img.shields.io/packagist/v/decodelabs/wellspring.svg?style=flat)](https://packagist.org/packages/decodelabs/wellspring)
[![Total Downloads](https://img.shields.io/packagist/dt/decodelabs/wellspring.svg?style=flat)](https://packagist.org/packages/decodelabs/wellspring)
[![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/decodelabs/wellspring/integrate.yml?branch=develop)](https://github.com/decodelabs/wellspring/actions/workflows/integrate.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-44CC11.svg?longCache=true&style=flat)](https://github.com/phpstan/phpstan)
[![License](https://img.shields.io/packagist/l/decodelabs/wellspring?style=flat)](https://packagist.org/packages/decodelabs/wellspring)

### PHP autoload management tools

Wellspring provides simple tools to manage and configure autoloaders in PHP.

---

## Installation

This package requires PHP 8.4 or higher.

Install via Composer:

```bash
composer require decodelabs/wellspring
```

## Usage

Wellspring offers an easy to use wrapper around SPL autoload functions, providing extra functionality such as priority ordering and deduplication.

The API is simple and intuitive, allowing you to register autoloaders with a priority level - the higher the priority, the earlier the autoloader will be called. Loaders are _grouped_ by priority but maintain the first-come, first-served order within each group.

Loaders registered via the built in SPL functions are automatically assigned the `Priority::Medium` priority, allowing users of Wellspring to easily register other loaders before or after them using only the Priority mechanism.

The registered list of loaders is automatically remapped on the fly when necessary (even when `spl_autoload_register()` and `spl_autoload_unregister()` are used directly), ensuring edge-case functionality does not interfere with the intended load order.

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

spl_autoload_register(function(string $class) {
    // This will get called second
});

Wellspring::register(function(string $class) {
    // This will get called last
}, Priority::Low);

Wellspring::register(function(string $class) {
    // This will get called first
}, Priority::High);

spl_autoload_register(function(string $class) {
    // This will get called third
});

spl_autoload_call('test');
```

### Error handling

Wellspring does not attempt to handle errors or exceptions that may be thrown by loaders. If a loader throws an error, it will be propagated up to the caller and should be handled by the application, in the same way as if the loader was registered directly via `spl_autoload_register()`.

## Deduplication

If static references (such as `['Class', 'method']` or `'Class::method'`) are registered multiple times, Wellspring will automatically deduplicate them, ensuring the same loader is not called multiple times for the same class.

Please note though, object instances are only deduplicated if they are the **same** instance, not if they are different instances of the same class as instances are considered unique. This also applies to `Closures` (both defined as classic anonymous functions and as first class callables such as `$this->method(...)`) as the _context_ of the closure is considered unique.



### Unregistering Loaders

If you need to unregister a loader, you can do so by passing the same callback or loader instance to the `unregister()` method. This applies whether the callback was registered via Wellspring or directly via `spl_autoload_register()`.

```php
Wellspring::unregister(function(string $class) {
    // This will be unregistered
});
```

It is also safe to use `spl_autoload_unregister()` directly to unregister a loader, Wellspring with automatically handle the necessary remapping of the queue the next time a class is loaded.

## Debugging

If you need to debug the current state of the autoloader queue, you can use the `dump()` method to output the current state of the queue.

```php
Wellspring::dump();
```

This will output a list of all registered loaders in order of priority:

```
[
    'ob:Closure(15)' => [
        'callback' => Loader,
        'priority' => Priority::High
    ],
    'ao:Composer\Autoload\ClassLoader(5)::loadclass' => [
        'callback' => [Composer\Autoload\ClassLoader, loadclass],
        'priority' => Priority::Medium
    ],
    'st:test' => [
        'callback' => Loader,
        'priority' => Priority::Low
    ]
]
```

You can also query the number of order checks and remaps that have been performed:

```php
Wellspring\QueueHandler::$checks;
Wellspring\QueueHandler::$remaps;
```

### Performance

Wellspring is designed to be performant and efficient, ensuring that managing the autoloader queue does not significantly impact performance. The `QueueHandler` works in the hot path of the autoloader system and leverages PHP's internal referencing of arrays to minimise memory allocations and churn.




## Licensing

Wellspring is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
