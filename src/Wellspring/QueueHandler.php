<?php

/**
 * @package Wellspring
 * @license http://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace DecodeLabs\Wellspring;

use DecodeLabs\Wellspring;

class QueueHandler
{
    public static int $checks = 0;
    public static int $remaps = 0;

    /**
     * @var array<callable>
     */
    private static ?array $functions = null;

    public function __invoke(
        string $class
    ): void {
        self::$checks++;
        $functions = spl_autoload_functions();

        if (
            // @phpstan-ignore-next-line
            $functions === false ||
            $functions === self::$functions
        ) {
            return;
        }

        $resetCheckQueue = false;
        $currentPriority = Priority::High;
        $resetLows = $resetHighs = false;
        $lows = $highs = $ids = [];

        foreach ($functions as $i => $function) {
            // Check queue
            if ($function === $this) {
                if ($i !== 0) {
                    $resetCheckQueue = true;
                }
                continue;
            }

            if ($function instanceof Loader) {
                $id = $function->id;
            } else {
                $id = Wellspring::identifyCallback($function);
            }

            if (in_array($id, $ids)) {
                continue;
            }

            $ids[] = $id;

            // Check priority
            if ($function instanceof Loader) {
                $priority = $function->priority;
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
                spl_autoload_register($function, prepend: true);
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
            spl_autoload_unregister($this);
            spl_autoload_register($this, prepend: true);
        }

        self::$functions = spl_autoload_functions();
        self::$remaps++;

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
