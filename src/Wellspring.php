<?php

/**
 * @package Wellspring
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs;

use DecodeLabs\Wellspring\Loader;
use DecodeLabs\Wellspring\Priority;
use DecodeLabs\Wellspring\QueueHandler;

final class Wellspring
{
    private static bool $initialized = false;
    public static int $initCall = 0;
    public static int $orderCall = 0;

    /**
     * @var array<string, Loader>
     */
    private static array $loaders = [];

    private static QueueHandler $queueHandler;

    public static function register(
        callable $callback,
        string|Priority|null $priority = null
    ): void {
        // Normalize callback / loader
        if ($callback instanceof Loader) {
            $loader = $callback;
        } else {
            $loader = new Loader($callback, $priority);
        }

        // Check if loader is already registered
        if (isset(self::$loaders[$loader->id])) {
            return;
        }

        // Register loader
        self::$loaders[$loader->id] = $loader;

        spl_autoload_register(
            $loader,
            true,
            $loader->priority === Priority::High
        );


        // Ensure queue handler is always registered first
        if (
            !self::$initialized ||
            $loader->priority === Priority::High
        ) {
            if (
                self::$initialized &&
                isset(self::$queueHandler)
            ) {
                spl_autoload_unregister(self::$queueHandler);
            }

            if (!isset(self::$queueHandler)) {
                self::$queueHandler = new QueueHandler();
            }

            self::$initialized = true;
            spl_autoload_register(self::$queueHandler, true, true);
        }
    }

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

    public static function identifyCallback(
        callable $callback
    ): string {
        if ($callback instanceof Loader) {
            return $callback->id;
        }

        if (is_object($callback)) {
            return 'spl:' . spl_object_hash($callback);
        }

        if (is_array($callback)) {
            $output = 'pn:';

            if (is_object($callback[0])) {
                $output .= get_class($callback[0]) . '(' . spl_object_id($callback[0]) . ')';
            } elseif (is_string($callback[0])) {
                $output .= $callback[0];
            }

            if (is_string($callback[1])) {
                $output .= '::' . $callback[1];
            }

            return $output;
        }

        if (is_string($callback)) {
            return 'pn:' . $callback;
        }

        return 'fn:' . md5(serialize($callback));
    }

    /**
     * @return array<string,array{callback:callable,priority:Priority}>
     */
    public static function dump(): array
    {
        $output = [];
        $functions = spl_autoload_functions();

        // @phpstan-ignore-next-line
        if ($functions === false) {
            return $output;
        }

        foreach ($functions as $function) {
            if ($function === self::$queueHandler) {
                continue;
            }

            $id = self::identifyCallback($function);

            if ($function instanceof Loader) {
                $priority = $function->priority;
            } else {
                $priority = Priority::Medium;
            }

            $output[$id] = [
                'callback' => $function,
                'priority' => $priority,
            ];
        }

        return $output;
    }
}
