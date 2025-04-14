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

Install via Composer:

```bash
composer require decodelabs/wellspring
```

## Usage

Use <code>Wellspring</code> to register autoloaders with a Priority level - the higher the priority, the earlier the autoloader will be called.

The library automatically remaps loaders on the fly when necessary (even when <code>spl_autoload_register()</code> and <code>spl_autoload_unregister()</code> are used directly), ensuring edge-case functionality does not interfere with the intended load order.

Any loaders registered without a priority default to <code>Priority::Medium</code>, and any with matching priorities will be called in the order they were registered.

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

Wellspring::register(function(string $class) {
    // This will get called last
}, Priority::Low);

Wellspring::register(function(string $class) {
    // This will get called first
}, Priority::High);

spl_autoload_register(function(string $class) {
    // This will get called second
});

spl_autoload_call('test');
```


## Licensing

Wellspring is licensed under the MIT License. See [LICENSE](./LICENSE) for the full license text.
