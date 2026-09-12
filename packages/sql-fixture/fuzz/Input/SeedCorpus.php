<?php

declare(strict_types=1);

namespace Fuzz\Input;

use RuntimeException;

/**
 * Adds maintained regression seeds without replacing the evolving raw-input corpus.
 */
final class SeedCorpus
{
    /**
     * Creates the corpus expected by the Composer fuzz command and copies missing seeds.
     *
     * @throws RuntimeException When the corpus cannot be initialized
     */
    public static function prepare(string $target): void
    {
        $root = dirname(__DIR__);
        $destination = $root . '/corpus/' . $target;
        if (!is_dir($destination) && !mkdir($destination, 0777, true)) {
            throw new RuntimeException('Cannot create fuzz corpus: ' . $destination);
        }
        $seeds = glob($root . '/seeds/' . $target . '/*');
        if ($seeds === false) {
            throw new RuntimeException('Cannot read fuzz seeds: ' . $target);
        }
        foreach ($seeds as $seed) {
            $path = $destination . '/' . basename($seed);
            if (!is_file($path) && !copy($seed, $path)) {
                throw new RuntimeException('Cannot copy fuzz seed: ' . $seed);
            }
        }
    }
}
