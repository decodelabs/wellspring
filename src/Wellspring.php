<?php

/**
 * @package Veneer
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs;

use DecodeLabs\Wellspring\Loader;
use DecodeLabs\Wellspring\Priority;

final class Wellspring
{
    private static bool $initialized = false;
    public static int $initCall = 0;
    public static int $orderCall = 0;

    /**
     * @var array<string, Loader>
     */
    private static array $loaders = [];

    /**
     * @var array<callable>
     */
    private static ?array $functions = null;

    /**
     * Register SPL loader with priority
     */
    public static function register(
        callable $callback,
        string|Priority|null $priority = null
    ): void {
        if ($priority === null) {
            $priority = Priority::Medium;
        } elseif (is_string($priority)) {
            $priority = Priority::from($priority);
        }

        $loader = new Loader($callback, $priority);

        if (isset(self::$loaders[$loader->getId()])) {
            return;
        }

        self::$loaders[$loader->getId()] = $loader;

        spl_autoload_register(
            $loader,
            true,
            $loader->getPriority() === Priority::High
        );


        if (
            !self::$initialized ||
            $loader->getPriority() === Priority::High
        ) {
            if (self::$initialized) {
                spl_autoload_unregister([self::class, 'checkQueue']);
            }

            self::$initialized = true;
            spl_autoload_register([self::class, 'checkQueue'], true, true);
        }
    }

    /**
     * Unregister SPL loader
     */
    public static function unregister(
        callable $callback
    ): void {
        $id = self::identifyCallback($callback);

        if (isset(self::$loaders[$id])) {
            spl_autoload_unregister(self::$loaders[$id]);
            unset(self::$loaders[$id]);
            return;
        }

        spl_autoload_unregister($callback);
    }

    /**
     * Get list of registered loaders
     *
     * @return array<string, Loader>
     */
    public static function getLoaders(): array
    {
        return self::$loaders;
    }

    /**
     * Create unique ID for callback
     */
    public static function identifyCallback(
        callable $callback
    ): string {
        if (is_object($callback)) {
            return 'spl:' . spl_object_hash($callback);
        }

        if (is_array($callback)) {
            return 'pn:' . implode('::', $callback);
        }

        if (is_string($callback)) {
            return 'pn:' . $callback;
        }

        return 'fn:' . md5(serialize($callback));
    }

    /**
     * Queue checker loader
     */
    private static function checkQueue(
        string $class
    ): void {
        self::$initCall++;
        $functions = spl_autoload_functions();

        if ($functions === self::$functions) {
            return;
        }

        $resetCheckQueue = false;
        $currentPriority = Priority::High;
        $resetLows = $resetHighs = false;
        $lows = $highs = [];

        foreach ($functions as $i => $function) {
            // Check queue
            if ($function === [self::class, 'checkQueue']) {
                if ($i !== 0) {
                    $resetCheckQueue = true;
                }
                continue;
            }

            // Check priority
            if ($function instanceof Loader) {
                $priority = $function->getPriority();
            } else {
                $priority = Priority::Medium;
            }

            switch ($priority) {
                case Priority::High:
                    if ($currentPriority !== Priority::High) {
                        $highs[] = $function;
                        $resetHighs = true;
                    }
                    break;

                case Priority::Medium:
                    if ($currentPriority === Priority::High) {
                        $currentPriority = Priority::Medium;
                    } elseif ($currentPriority === Priority::Low) {
                        $resetLows = true;
                    }
                    break;

                case Priority::Low:
                    $currentPriority = Priority::Low;
                    $lows[] = $function;
                    break;
            }
        }


        // Reset highs
        if ($resetHighs) {
            $resetCheckQueue = true;

            foreach ($highs as $function) {
                spl_autoload_unregister($function);
                spl_autoload_register($function, true, true);
            }
        }

        // Reset lows
        if ($resetLows) {
            foreach ($lows as $function) {
                spl_autoload_unregister($function);
                spl_autoload_register($function);
            }
        }

        // Check queue
        if ($resetCheckQueue) {
            spl_autoload_unregister([self::class, 'checkQueue']);
            spl_autoload_register([self::class, 'checkQueue'], true, true);
        }

        self::$functions = spl_autoload_functions();
        self::$orderCall++;

        if (
            $resetHighs ||
            $resetLows ||
            $resetCheckQueue
        ) {
            // Run from the top again
            spl_autoload_call($class);
        }
    }
}
