<?php

/**
 * @package Wellspring
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Wellspring;

use Closure;
use DecodeLabs\Wellspring;

final class Loader
{
    public private(set) string $id;
    public private(set) Closure $callback;
    public private(set) Priority $priority = Priority::Medium;


    public function __construct(
        callable $callback,
        string|Priority|null $priority
    ) {
        $this->id = Wellspring::identifyCallback($callback);
        $this->callback = Closure::fromCallable($callback);

        if ($priority === null) {
            $priority = Priority::Medium;
        } elseif (is_string($priority)) {
            $priority = Priority::from($priority);
        }

        $this->priority = $priority;
    }

    public function isCallback(
        callable $callback
    ): bool {
        return Wellspring::identifyCallback($callback) === $this->id;
    }

    public function __invoke(
        string $class
    ): void {
        ($this->callback)($class);
    }
}
