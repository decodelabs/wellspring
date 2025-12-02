# Wellspring — Package Specification

> **Cluster:** `runtime`  
> **Language:** `php`  
> **Milestone:** `m1`  
> **Repo:** `https://github.com/decodelabs/wellspring`  
> **Role:** Autoloader manager

## Overview

### Purpose

Wellspring provides simple tools to manage and configure autoloaders in PHP. It offers an easy-to-use wrapper around SPL autoload functions, providing extra functionality such as priority ordering and deduplication.

Key features:
- **Priority-based ordering**: Register autoloaders with priority levels (High, Medium, Low)
- **Automatic deduplication**: Ensures each autoloader is only registered once
- **Queue management**: Automatically remaps autoloader queue to maintain priority order
- **SPL integration**: Works seamlessly with direct SPL autoload function calls
- **Debugging support**: Dump current autoloader queue state for debugging
- **Performance tracking**: Track queue checks and remaps for performance monitoring

### Non-Goals

- Wellspring does not provide autoloader implementations (only management).
- It does not handle class loading or file resolution.
- It does not provide PSR-4 or other autoloading standards implementation.
- It does not handle autoloader caching or optimization.
- It does not provide autoloader testing or validation tools.

## Role in the Ecosystem

### Cluster & Positioning

Wellspring belongs to the **runtime** cluster, providing foundational autoloader management capabilities. It serves as a utility for managing the autoloader queue with priority ordering and deduplication, essential for complex applications with multiple autoloaders.

### Usage Contexts

- **Priority ordering**: Registering autoloaders with specific priority levels
- **Deduplication**: Preventing duplicate autoloader registrations
- **Queue management**: Maintaining autoloader order when SPL functions are used directly
- **Debugging**: Inspecting autoloader queue state
- **Performance monitoring**: Tracking autoloader queue operations

## Public Surface

### Key Types

- **`Wellspring`** (final class): Main facade class providing static methods for autoloader registration and management.

- **`Priority`** (enum): Enum defining autoloader priority levels (Low, Medium, High). Provides integer conversion.

- **`Loader`** (final class): Loader wrapper class wrapping callables for autoloader registration. Provides callback identity and priority management.

- **`QueueHandler`** (class): Queue handler class managing autoloader queue order. Automatically remaps queue when necessary.

- **`CallbackType`** (enum): Enum defining callback types (Object, String, ObjectArray, StringArray, SerializedArray, SerializedFunction).

- **`Source`** (enum): Enum defining autoloader source (Wellspring, SPL).

### Main Entry Points

**Wellspring (Static Facade):**
- `Wellspring::register(callable $callback, string|Priority|null $priority = null): void` — Register autoloader with priority
- `Wellspring::unregister(callable $callback): void` — Unregister autoloader
- `Wellspring::identifyCallback(callable $callback): string` — Identify callback for deduplication
- `Wellspring::dump(): array` — Dump current autoloader queue state

**Priority Enum:**
- `Priority::Low` — Low priority (called last)
- `Priority::Medium` — Medium priority (default, called second)
- `Priority::High` — High priority (called first)
- `$priority->toInt(): int` — Convert priority to integer (0=Low, 1=Medium, 2=High)

**Loader:**
- `new Loader(callable $callback, string|Priority|null $priority)` — Constructor
- `$loader->id` — Callback identifier (readonly property)
- `$loader->callback` — Wrapped callback closure (readonly property)
- `$loader->priority` — Priority level (readonly property)
- `$loader->isCallback(callable $callback): bool` — Check if callback matches loader
- `$loader->__invoke(string $class): void` — Invoke loader for class

**QueueHandler:**
- `new QueueHandler()` — Constructor
- `QueueHandler::$checks` — Number of queue checks performed (static property)
- `QueueHandler::$remaps` — Number of queue remaps performed (static property)
- `$handler->__invoke(string $class): void` — Handle autoload queue check and remap

**CallbackType Enum:**
- `CallbackType::Object` — Object callback (ob)
- `CallbackType::String` — String callback (st)
- `CallbackType::ObjectArray` — Object array callback (ao)
- `CallbackType::StringArray` — String array callback (as)
- `CallbackType::SerializedArray` — Serialized array callback (ax)
- `CallbackType::SerializedFunction` — Serialized function callback (fx)
- `CallbackType::from(string $prefix): CallbackType` — Create from prefix

**Source Enum:**
- `Source::Wellspring` — Registered via Wellspring
- `Source::SPL` — Registered via SPL functions

## Dependencies

### Decode Labs

None.

### External

- **PHP**: See `composer.json` for supported PHP versions.

## Behaviour & Contracts

### Invariants

- Queue handler always registered first in autoloader queue.
- Loaders registered via Wellspring wrapped in `Loader` instances.
- Loaders registered via SPL assigned `Priority::Medium` priority.
- Priority ordering: High → Medium → Low.
- Within each priority group, loaders maintain first-come, first-served order.
- Callback deduplication based on callback identity.
- Queue automatically remapped when order changes.
- Loader callbacks have void return type.

### Input & Output Contracts

**Registration:**
- `register()` accepts callable and optional priority.
- Priority can be `Priority` enum, string (`'low'`, `'medium'`, `'high'`), or `null` (defaults to `Priority::Medium`).
- Callable wrapped in `Loader` instance.
- Loader registered via `spl_autoload_register()`.
- High priority loaders prepended (registered first).
- Medium and Low priority loaders appended (registered after).
- Duplicate callbacks skipped (deduplication).
- Queue handler registered first if not already registered.

**Unregistration:**
- `unregister()` accepts callable or `Loader` instance.
- Callback identified via `identifyCallback()`.
- If registered via Wellspring, loader unregistered from SPL and removed from internal registry.
- If registered via SPL, callback unregistered directly.
- Safe to use `spl_autoload_unregister()` directly.

**Callback Identification:**
- `identifyCallback()` generates unique identifier for callable.
- Object callables: `'ob:ClassName(objectId)'`.
- String callables: `'st:functionname'` (lowercase).
- String static methods: `'as:ClassName::methodname'` (lowercase).
- Object array callables: `'ao:ClassName(objectId)::methodname'` (lowercase).
- Other arrays: `'ax:hash'` (serialized hash).
- Other callables: `'fx:hash'` (serialized hash).
- Class and method names normalized to lowercase.
- Object instances identified by `spl_object_id()`.

**Queue Management:**
- Queue handler checks queue order on each autoload call.
- Queue remapped if order incorrect.
- High priority loaders moved to front.
- Low priority loaders moved to end.
- Queue handler always kept first.
- Remapping triggers re-run of autoload chain.

**Deduplication:**
- Callbacks deduplicated by identifier.
- Functions: matched by name (case-insensitive).
- Static methods: matched by class and method (case-insensitive).
- Instance methods: matched by object instance and method.
- Closures: matched by object instance (each closure is unique).
- Invokable objects: matched by object instance.
- Same callable registered multiple times: only first registration kept.

**Priority Handling:**
- High priority: Loaders called first, prepended to queue.
- Medium priority: Loaders called second, default for SPL-registered loaders.
- Low priority: Loaders called last, appended to queue.
- Priority groups maintain first-come, first-served order within group.

**Dump Output:**
- `dump()` returns array of autoloader information.
- Array keyed by callback identifier.
- Each entry contains:
  - `callback`: Callable or `Loader` instance
  - `priority`: Priority enum value
  - `type`: CallbackType enum value
  - `source`: Source enum value (Wellspring or SPL)
- Queue handler excluded from output.

**Performance Tracking:**
- `QueueHandler::$checks`: Incremented on each queue check.
- `QueueHandler::$remaps`: Incremented on each queue remap.
- Counters accessible for performance monitoring.

## Error Handling

- **No exceptions thrown**: Wellspring does not intercept exceptions from user autoloaders.
- **SPL behavior**: SPL stops autoload chain automatically when exception thrown.
- **Invalid priority**: String priority converted via `Priority::from()` (throws if invalid).
- **Queue handler errors**: Queue handler errors handled internally, autoload chain continues.

## Configuration & Extensibility

### Custom Priority

Use priority enum or string:

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

Wellspring::register($callback, Priority::High);
Wellspring::register($callback, 'high');
Wellspring::register($callback); // Defaults to Medium
```

### Custom Loader

Create loader instance directly:

```php
use DecodeLabs\Wellspring\Loader;
use DecodeLabs\Wellspring\Priority;

$loader = new Loader($callback, Priority::High);
Wellspring::register($loader);
```

### Direct SPL Usage

Safe to use SPL functions directly:

```php
spl_autoload_register($callback); // Assigned Medium priority
spl_autoload_unregister($callback); // Handled automatically
```

## Interactions with Other Packages

None. Wellspring is a standalone package with no dependencies.

## Usage Examples

### Basic Registration

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

spl_autoload_register(function(string $class) {
    // This will get called second (Medium priority)
});

Wellspring::register(function(string $class) {
    // This will get called last (Low priority)
}, Priority::Low);

Wellspring::register(function(string $class) {
    // This will get called first (High priority)
}, Priority::High);

spl_autoload_register(function(string $class) {
    // This will get called third (Medium priority)
});

spl_autoload_call('test');
```

### Priority Ordering

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

// High priority - called first
Wellspring::register($callback1, Priority::High);

// Medium priority - called second (default)
Wellspring::register($callback2, Priority::Medium);
Wellspring::register($callback3); // Also Medium

// Low priority - called last
Wellspring::register($callback4, Priority::Low);
```

### Deduplication

```php
use DecodeLabs\Wellspring;

$callback = function(string $class) {
    // Autoload logic
};

Wellspring::register($callback);
Wellspring::register($callback); // Ignored - already registered

spl_autoload_register($callback); // Also ignored - same callback
```

### Unregistering

```php
use DecodeLabs\Wellspring;

$callback = function(string $class) {
    // Autoload logic
};

Wellspring::register($callback);
Wellspring::unregister($callback); // Unregistered

// Or use SPL directly
spl_autoload_unregister($callback); // Also works
```

### Debugging

```php
use DecodeLabs\Wellspring;

$dump = Wellspring::dump();
/*
[
    'ob:Closure(15)' => [
        'callback' => Loader,
        'priority' => Priority::High,
        'type' => CallbackType::Object,
        'source' => Source::Wellspring,
    ],
    'ao:Composer\Autoload\ClassLoader(5)::loadclass' => [
        'callback' => [Composer\Autoload\ClassLoader, loadclass],
        'priority' => Priority::Medium,
        'type' => CallbackType::ObjectArray,
        'source' => Source::SPL,
    ],
    'st:test' => [
        'callback' => Loader,
        'priority' => Priority::Low,
        'type' => CallbackType::String,
        'source' => Source::Wellspring,
    ]
]
*/
```

### Performance Tracking

```php
use DecodeLabs\Wellspring\QueueHandler;

// Check queue operations
echo QueueHandler::$checks; // Number of queue checks
echo QueueHandler::$remaps; // Number of queue remaps
```

### Callback Identification

```php
use DecodeLabs\Wellspring;

// Object callback
$id1 = Wellspring::identifyCallback($object);
// 'ob:ClassName(123)'

// String callback
$id2 = Wellspring::identifyCallback('function_name');
// 'st:function_name'

// Static method
$id3 = Wellspring::identifyCallback(['Class', 'method']);
// 'as:class::method'

// Object method
$id4 = Wellspring::identifyCallback([$object, 'method']);
// 'ao:ClassName(123)::method'
```

## Implementation Notes (for Contributors)

### Wellspring Implementation

- Wellspring uses static class with internal registry.
- Loaders stored in array keyed by callback identifier.
- Queue handler registered first and kept first.
- Registration wraps callables in `Loader` instances.
- Deduplication checked before registration.

### Loader Implementation

- Loader wraps callable in `Closure` for consistent invocation.
- Callback identifier generated via `identifyCallback()`.
- Priority normalized from string or enum.
- `__invoke()` forwards to wrapped callback with void return type.

### QueueHandler Implementation

- Queue handler checks queue order on each autoload call.
- Queue compared to cached state.
- If order incorrect, queue remapped.
- High priority loaders moved to front.
- Low priority loaders moved to end.
- Queue handler always kept first.
- Remapping triggers re-run of autoload chain.

### Callback Identification

- Object callables: Class name + object ID.
- String callables: Function name (lowercase).
- Static methods: Class name + method name (lowercase).
- Object methods: Class name + object ID + method name (lowercase).
- Other callables: Serialized hash.
- Class and method names normalized to lowercase for matching.

### Priority Handling

- Priority enum values: Low (0), Medium (1), High (2).
- High priority: Prepended to queue (`prepend: true`).
- Medium/Low priority: Appended to queue (`prepend: false`).
- Within priority groups, order maintained by SPL registration order.

### Deduplication

- Callbacks deduplicated by identifier.
- Functions matched by name (case-insensitive).
- Static methods matched by class and method (case-insensitive).
- Instance methods matched by object instance and method.
- Closures matched by object instance (each closure unique).
- Same callback registered multiple times: only first kept.

### Performance

- Queue handler uses PHP's internal array referencing.
- Minimal memory allocations and churn.
- Queue cached to avoid unnecessary checks.
- Remapping only when order changes.

## Testing & Quality

**Current Status:**
- Code quality: 5/5
- README quality: 5/5
- Documentation: 0/5 (no formal docs yet)
- Tests: 0/5 (no test suite yet)

**Testing Considerations:**
- Wellspring should be tested for:
  - Registration (with different priorities)
  - Unregistration (Wellspring and SPL registered)
  - Deduplication (various callback types)
  - Priority ordering (High → Medium → Low)
  - Queue remapping (when order changes)
  - Queue handler positioning (always first)

- Loader should be tested for:
  - Callback wrapping
  - Priority normalization
  - Callback identification
  - Invocation forwarding

- QueueHandler should be tested for:
  - Queue checking
  - Queue remapping
  - Priority group ordering
  - Queue handler positioning

- Callback identification should be tested for:
  - Object callables
  - String callables
  - Static methods
  - Object methods
  - Closures
  - Invokable objects
  - Case insensitivity

- Deduplication should be tested for:
  - Function callables
  - Static method callables
  - Instance method callables
  - Closure callables
  - Multiple registrations

- Edge cases should be tested for:
  - Empty queue
  - Single loader
  - All same priority
  - Mixed SPL and Wellspring registrations
  - Direct SPL unregister
  - Queue handler errors
  - Performance under load

## Roadmap & Future Ideas

- **Performance optimization**: Further optimization for high-frequency autoload operations
- **Better debugging**: Enhanced debugging tools and diagnostics
- **Autoloader validation**: Validation of autoloader callbacks
- **Queue statistics**: More detailed statistics and metrics
- **Autoloader testing**: Tools for testing autoloader behavior

## References

- Package repository: https://github.com/decodelabs/wellspring
- Composer package: https://packagist.org/packages/decodelabs/wellspring
- PHP SPL autoloading: https://www.php.net/manual/en/language.oop5.autoload.php
- Related standards:
  - PSR-4: Autoloading Standard
