<?php

/**
 * @package Veneer
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


    /**
     * Init with callback and priority
     */
    public function __construct(
        callable $callback,
        Priority $priority
    ) {
        $this->id = Wellspring::identifyCallback($callback);
        $this->callback = Closure::fromCallable($callback);
        $this->priority = $priority;
    }

    /**
     * Get ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Does callback match check value?
     */
    public function isCallback(
        callable $callback
    ): bool {
        return Wellspring::identifyCallback($callback) === $this->id;
    }

    /**
     * Get callback
     */
    public function getCallback(): Closure
    {
        return $this->callback;
    }

    /**
     * Get priority
     */
    public function getPriority(): Priority
    {
        return $this->priority;
    }

    /**
     * Invoke callback
     */
    public function __invoke(
        string $class
    ): void {
        ($this->callback)($class);
    }
}
