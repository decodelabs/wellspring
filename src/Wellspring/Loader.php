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
    private string $id;
    private Closure $callback;
    private Priority $priority = Priority::Medium;


    public function __construct(
        callable $callback,
        Priority $priority
    ) {
        $this->id = Wellspring::identifyCallback($callback);
        $this->callback = Closure::fromCallable($callback);
        $this->priority = $priority;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isCallback(
        callable $callback
    ): bool {
        return Wellspring::identifyCallback($callback) === $this->id;
    }

    public function getCallback(): Closure
    {
        return $this->callback;
    }

    public function getPriority(): Priority
    {
        return $this->priority;
    }

    public function __invoke(
        string $class
    ): void {
        ($this->callback)($class);
    }
}
