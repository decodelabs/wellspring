# Wellspring — Package Specification

> **Cluster:** `runtime`  
> **Language:** `php`  
> **Milestone:** `m1`  
> **Repo:** `https://github.com/decodelabs/wellspring`  
> **Role:** Autoloader manager

This document describes the purpose, contracts, and design of **Wellspring** within the Decode Labs ecosystem.

It is aimed at:

- Developers **using** Wellspring in their own applications or libraries.
- Contributors **maintaining or extending** Wellspring.
- Tools and AI assistants that need to reason about its behaviour.

---

## 1. Overview

### 1.1 Purpose

Wellspring provides **PHP autoload management tools** that extend PHP's built-in SPL autoload functions with priority-based ordering, automatic deduplication, and queue management. It wraps SPL autoload functions (`spl_autoload_register()`, `spl_autoload_unregister()`) to provide a more structured and predictable autoloader management system while remaining fully compatible with direct SPL usage.

Wellspring enables Decode Labs packages and applications to register autoloaders with explicit priority control (High, Medium, Low), prevent duplicate autoloader registration, and maintain autoloader order even when SPL functions are used directly by other code.

### 1.2 Non-Goals

Wellspring does **not**:

- Implement its own autoloading mechanism (it wraps and manages SPL autoloaders).
- Replace Composer's autoloader or other existing autoloaders.
- Provide class file discovery or namespace-to-path mapping.
- Handle class loading errors or exceptions (exceptions from user autoloaders propagate normally).

Wellspring is a **management layer** for autoloaders, not an autoloader implementation itself.

---

## 2. Role in the Ecosystem

### 2.1 Cluster & Positioning

- **Cluster:** `runtime` (see Chorus taxonomy)
- Wellspring is a **foundational utility** used early in application bootstrap to manage autoloader registration and ordering. It sits at a very low level in the dependency graph with no dependencies, making it safe to use from almost anywhere in the stack.

### 2.2 Typical Usage Contexts

Typical places Wellspring appears:

- **Application bootstrap** to register custom autoloaders with specific priorities.
- **Library initialization** to ensure library autoloaders run before or after Composer's autoloader.
- **Development tools** that need to inject autoloaders for debugging or code generation.
- **Testing frameworks** that need to control autoloader order for test isolation.

Wellspring is intended to be used whenever a Decode Labs package or application needs to register autoloaders with explicit priority control, prevent duplicate autoloader registration, or maintain autoloader order even when SPL functions are used directly.

---

## 3. Public Surface

> This section focuses on the conceptual API, not every symbol.

### 3.1 Key Types

The primary public types are:

- `DecodeLabs\Wellspring`
  Main entry point for registering and managing autoloaders. Provides static methods for registration, unregistration, debugging, and callable identification.

- `DecodeLabs\Wellspring\Loader`
  Wrapper around a callable autoloader that tracks priority and provides identity for deduplication. Implements `__invoke(string $class): void` to match SPL autoloader signature. Can be instantiated directly or created automatically by `Wellspring::register()`.

- `DecodeLabs\Wellspring\Priority`
  Enum representing autoloader priority levels: `High`, `Medium`, `Low`. Provides `toInt()` method for integer conversion. Loaders registered via SPL functions directly are assigned `Medium` priority. PHP backed enum provides `from(string)` and `tryFrom(string)` methods automatically.

- `DecodeLabs\Wellspring\QueueHandler`
  Internal handler that monitors and remaps the autoloader queue. Provides static counters (`$checks`, `$remaps`) for debugging queue management activity.

- `DecodeLabs\Wellspring\CallbackType`
  Enum representing types of callable identifiers used for debugging: `Object`, `String`, `ObjectArray`, `StringArray`, `SerializedArray`, `SerializedFunction`.

- `DecodeLabs\Wellspring\Source`
  Enum indicating whether a loader was registered via Wellspring or directly via SPL functions: `Wellspring`, `SPL`.

### 3.2 Main Entry Points

The main usage pattern is registering autoloaders via static methods:

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

// Register with priority
Wellspring::register(function(string $class) {
    // Autoload logic
}, Priority::High);

// Register with default (Medium) priority
Wellspring::register(function(string $class) {
    // Autoload logic
});

// Unregister a loader
Wellspring::unregister($callback);

// Debug current queue state
$dump = Wellspring::dump();

// Identify a callable for deduplication
$id = Wellspring::identifyCallback($callback);
```

Wellspring automatically manages the autoloader queue order and deduplication, ensuring loaders run in the correct priority order even when mixed with direct SPL usage.

---

## 4. Dependencies

### 4.1 Direct Decode Labs Dependencies

From `composer.json`:

- None (Wellspring has no required Decode Labs dependencies).

Wellspring is designed to be **dependency-free** and usable at the earliest stages of application bootstrap.

### 4.2 External Dependencies

Wellspring requires:

- **PHP 8.4+** (see `composer.json` for supported PHP versions).

No external libraries or packages are required for runtime operation.

---

## 5. Behaviour & Contracts

### 5.1 Invariants

- `Wellspring::register()` **always deduplicates** loaders by callable identity (same callable registered multiple times is ignored).
- Loaders registered via `Wellspring::register()` with `Priority::High` **always run before** loaders registered via SPL functions directly (which get `Medium` priority).
- Loaders registered via `Wellspring::register()` with `Priority::Low` **always run after** loaders registered via SPL functions directly.
- The `QueueHandler` **always runs first** in the autoloader queue to monitor and remap the queue when needed.
- Callable identity is **case-insensitive** for class and method names (e.g., `['Class', 'method']` and `['class', 'Method']` are considered the same).
- Object instances are **only deduplicated if they are the same instance** (different instances of the same class are considered unique).
- Closures are **always considered unique** (even if they have the same code, their context is unique).

### 5.2 Input & Output Contracts

- `Wellspring::register(callable $callback, string|Priority|null $priority)` accepts:
  - `$callback`: Any callable (function, method, closure, invokable object, or `Loader` instance).
  - `$priority`: Optional priority (`Priority::High`, `Priority::Medium`, `Priority::Low`, or string `'high'`, `'medium'`, `'low'`). Defaults to `Priority::Medium`.
  - Returns: `void`

- `Wellspring::unregister(callable $callback)` accepts:
  - `$callback`: The same callable (or `Loader` instance) that was registered. Works for both Wellspring-registered and SPL-registered loaders.
  - Returns: `void`

- `Wellspring::dump()` returns:
  - Array keyed by callable identity (string), each containing:
    - `callback`: The callable or `Loader` instance.
    - `priority`: The `Priority` enum value.
    - `type`: The `CallbackType` enum value.
    - `source`: The `Source` enum value (`Wellspring` or `SPL`).

- `Wellspring::identifyCallback(callable $callback)` returns:
  - String identifier for the callable, used for deduplication. Format depends on callable type:
    - Objects: `'ob:ClassName(objectId)'`
    - Strings: `'st:functionname'`
    - Object arrays: `'ao:ClassName(objectId)::methodname'`
    - String arrays: `'as:ClassName::methodname'`
    - Other: `'ax:hash'` or `'fx:hash'`

### 5.3 Queue Management

The `QueueHandler` automatically:

- **Monitors** the autoloader queue on each autoload call (runs first in the queue).
- **Detects** when the queue order is incorrect (e.g., High priority loaders after Medium, Low priority loaders before Medium, or itself not first).
- **Remaps** the queue by unregistering and re-registering loaders in the correct order.
- **Maintains** first-come, first-served order within each priority group.
- **Increments** static counters (`$checks`, `$remaps`) for debugging.

This allows Wellspring to work correctly even when `spl_autoload_register()` or `spl_autoload_unregister()` are called directly by other code.

---

## 6. Error Handling

### 6.1 Exception Types

Wellspring does **not** throw exceptions for normal operations. It uses PHP's standard autoloader behaviour:

- **Exceptions from user autoloaders** propagate normally and stop the autoload chain (matching SPL behaviour).
- **Invalid priority strings** passed to `Priority::from()` throw `ValueError` if the string does not match a valid enum case (PHP backed enum behaviour).

### 6.2 Error Strategy

Wellspring is designed to be **non-intrusive**:

- It does not intercept or handle exceptions from user autoloaders.
- It does not validate callable signatures (any callable accepted by `spl_autoload_register()` is accepted).
- It gracefully handles edge cases (e.g., empty autoloader queue, missing callables) by falling back to SPL behaviour.
- Queue remapping errors are handled internally and do not affect the autoload chain.

---

## 7. Configuration & Extensibility

### 7.1 Configuration

Wellspring has **no configuration** beyond the priority parameter when registering loaders. It operates automatically once loaders are registered.

### 7.2 Extension Points

Wellspring supports extension via:

- **Custom `Loader` instances**: Create `Loader` instances directly with specific priorities and pass them to `register()`.
- **Direct SPL usage**: Wellspring monitors and remaps the queue even when SPL functions are used directly, ensuring consistent priority ordering.

Built-in types that may be extended:

- `Priority`: Enum with `High`, `Medium`, `Low` values and `toInt()` method.
- `CallbackType`: Enum identifying callable types for debugging.
- `Source`: Enum identifying whether a loader came from Wellspring or SPL.

---

## 8. Interactions with Other Packages

Wellspring is designed to be used by other packages:

- **`decodelabs/exceptional`**
  May use Wellspring to register autoloaders with specific priorities (e.g., to ensure exception class autoloading happens early).

Design assumptions:

- Wellspring is available **very early** in the stack and is considered **safe to use from any layer**.
- Other packages should not override Wellspring's core mechanisms, but may:
  - register their own autoloaders with appropriate priorities,
  - use `dump()` for debugging,
  - mix Wellspring registration with direct SPL usage (Wellspring will handle remapping).

---

## 9. Usage Examples

### 9.1 Basic Registration with Priority

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

// Register a high-priority autoloader
Wellspring::register(function(string $class) {
    if (str_starts_with($class, 'MyApp\\')) {
        require __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
    }
}, Priority::High);

// Register a low-priority fallback
Wellspring::register(function(string $class) {
    // Fallback logic
}, Priority::Low);
```

### 9.2 Mixed SPL and Wellspring Usage

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;

// Register via SPL (gets Medium priority)
spl_autoload_register(function(string $class) {
    // This runs second
});

// Register via Wellspring with High priority
Wellspring::register(function(string $class) {
    // This runs first
}, Priority::High);

// Register via SPL again (gets Medium priority)
spl_autoload_register(function(string $class) {
    // This runs third
});

// Register via Wellspring with Low priority
Wellspring::register(function(string $class) {
    // This runs last
}, Priority::Low);
```

### 9.3 Deduplication

```php
use DecodeLabs\Wellspring;

$loader = function(string $class) {
    // Autoload logic
};

// Register multiple times
Wellspring::register($loader);
Wellspring::register($loader);
Wellspring::register($loader);

// Only registered once - subsequent calls are ignored
```

### 9.4 Unregistering Loaders

```php
use DecodeLabs\Wellspring;

$loader = function(string $class) {
    // Autoload logic
};

Wellspring::register($loader);

// Later, unregister it
Wellspring::unregister($loader);

// Or unregister a SPL-registered loader
spl_autoload_register($loader);
Wellspring::unregister($loader);
```

### 9.5 Debugging the Queue

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Priority;
use DecodeLabs\Wellspring\QueueHandler;

// Register some loaders
Wellspring::register(function(string $class) {}, Priority::High);
spl_autoload_register(function(string $class) {});
Wellspring::register(function(string $class) {}, Priority::Low);

// Dump the queue
$dump = Wellspring::dump();
print_r($dump);

// Check queue handler stats
echo "Checks: " . QueueHandler::$checks . PHP_EOL;
echo "Remaps: " . QueueHandler::$remaps . PHP_EOL;
```

### 9.6 Using Loader Instances Directly

```php
use DecodeLabs\Wellspring;
use DecodeLabs\Wellspring\Loader;
use DecodeLabs\Wellspring\Priority;

$loader = new Loader(
    function(string $class) {
        // Autoload logic
    },
    Priority::High
);

Wellspring::register($loader);

// Check if a callback matches this loader
if ($loader->isCallback($someCallback)) {
    // Matches
}
```

---

## 10. Implementation Notes (For Contributors)

### 10.1 Internal Architecture

At a high level, Wellspring:

- **Wraps callables** in `Loader` instances that track priority and identity.
- **Registers loaders** via `spl_autoload_register()` with appropriate prepend flags based on priority (High priority loaders are prepended).
- **Monitors the queue** via `QueueHandler` which runs first in the autoloader chain (always prepended).
- **Identifies callables** using a consistent string format based on callable type (objects, strings, arrays, etc.).
- **Remaps the queue** when order violations are detected (High after Medium, Low before Medium, or QueueHandler not first).

Key implementation details:

- `Loader` wraps callables in `Closure::fromCallable()` to normalize them.
- `Wellspring::identifyCallback()` generates unique identifiers for different callable types using object IDs for instances and case-insensitive strings for class/method names.
- `QueueHandler` uses static state to track the last known queue state and detect changes.
- Queue remapping unregisters and re-registers loaders to maintain order within priority groups.
- The queue handler itself is always prepended to ensure it runs first.

### 10.2 Performance Considerations

- The `QueueHandler` runs on **every autoload call** but only performs remapping when order violations are detected.
- Queue checks are **lightweight** (array comparison) and remapping is **rare** (only when order is incorrect).
- Callable identification uses **efficient string operations** and object IDs (no reflection).
- Deduplication uses **array key lookup** (O(1)) based on callable identity.
- Queue remapping leverages PHP's internal array referencing to minimize memory allocations.

### 10.3 Gotchas & Historical Decisions

- **Closure identity**: Closures are always considered unique, even if they have identical code, because their context (bound variables, scope) is unique.
- **Object instance identity**: Only the same object instance is deduplicated; different instances of the same class are considered unique.
- **Static method references**: `['Class', 'method']` and `'Class::method'` are normalized and deduplicated case-insensitively.
- **Queue handler position**: The queue handler must run first to monitor the queue, so it's always prepended. If it's not first, it remaps itself to the front.
- **SPL compatibility**: Wellspring works with direct SPL usage by monitoring and remapping the queue, but loaders registered via SPL directly get `Medium` priority.
- **Priority enum**: PHP 8.4 backed enums automatically provide `from()` and `tryFrom()` methods, which are used to convert string priorities to enum values.

---

## 11. Testing & Quality

### 11.1 Testing Strategy

Tests should cover:

- Registration with different priorities (High, Medium, Low) and priority string conversion.
- Deduplication of various callable types (functions, methods, closures, objects).
- Queue ordering (High before Medium before Low) and correct execution order.
- Mixed SPL and Wellspring registration and compatibility.
- Unregistration of Wellspring and SPL-registered loaders.
- Queue remapping when order violations are detected (High after Medium, Low before Medium, QueueHandler not first).
- Queue handler statistics (`$checks`, `$remaps`) increment correctly.
- `dump()` output format and accuracy for all callable types and sources.
- Callable identification for all supported types (objects, strings, arrays, closures).
- Edge cases (empty queue, unregistering non-existent loaders, invalid priorities, etc.).

### 11.2 Quality Signals

From the Decode Labs package index (at time of writing):

- **Code:** 5
- **Readme:** 5
- **Docs:** Tracked centrally in Chorus
- **Tests:** Tracked centrally in Chorus

Wellspring is a **high-quality, dependency-free utility** that should be treated as a stable foundation for autoloader management across the Decode Labs ecosystem.

---

## 12. Roadmap & Future Ideas

Non-binding ideas:

- Additional priority levels (e.g., `VeryHigh`, `VeryLow`) for finer-grained control.
- Priority groups with numeric values for custom ordering.
- Autoloader performance metrics (e.g., call count, average execution time per loader).
- Integration with Composer autoloader to automatically assign priorities based on package dependencies.
- Support for conditional autoloaders (e.g., only load in development mode).
- Priority inheritance when registering `Loader` instances directly.

---

## 13. References

- **Chorus docs:**
  - Architecture principles
  - Package taxonomy & clusters
  - Backwards compatibility strategy (once published)

- **Related packages:**
  - `decodelabs/exceptional` (may use Wellspring for autoloader management)

- **Repository:**
  - `https://github.com/decodelabs/wellspring`

---

> This spec is intended to stay in sync with the **actual behaviour** of the package.
> When you make significant changes to the public surface or semantics, please update this document and, where applicable, add or update ADRs in Chorus.
