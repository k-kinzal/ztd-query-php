<?php

declare(strict_types=1);

namespace Tests\Generator;

use InvalidArgumentException;
use JsonException;
use LogicException;

/**
 * Replays independently seeded constructor samples for a selected public semantic type.
 */
final class Samples
{
    /**
     * @var class-string|null The property whose datasets share parsed constructor metadata
     */
    private static ?string $metadataClass = null;

    private static ?Metadata $metadata = null;

    /**
     * @return positive-int The number of independently reproducible samples per model
     * @throws InvalidArgumentException When the sample count is not positive
     */
    public function count(): int
    {
        $requested = getenv('MYSQL_MEMORY_PBT_SAMPLES');
        $samples = $requested === false ? 10 : filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($samples === false) {
            throw new InvalidArgumentException('MYSQL_MEMORY_PBT_SAMPLES must be a positive integer.');
        }
        return $samples;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param non-negative-int $sample One reproducible trial
     * @return T
     * @throws JsonException When a dependency inventory cannot be read
     * @throws LogicException When a type-specific generator violates its declared contract
     * @throws InvalidArgumentException When the sample count is not positive
     */
    public function sample(string $class, int $sample): object
    {
        $seed = getenv('MYSQL_MEMORY_PBT_SEED');
        $seed = $seed === false ? 'all-model-roots' : $seed;
        $catalogue = new Catalogue();
        if (self::$metadataClass !== $class) {
            self::$metadataClass = $class;
            self::$metadata = new Metadata();
        }
        $metadata = self::$metadata ??= new Metadata();
        $roots = $catalogue->roots();
        $index = array_search($class, $roots, true);
        $choices = new Choices($index === false ? $seed . ':' . $class . ':' . $sample : $seed . ':' . ($index + $sample * count($roots)));
        $choices->one($roots);
        $generator = str_replace(['SqlSemantics\\Platform\\MySql\\', 'SqlSemantics\\'], ['Tests\\Generator\\MySql\\', 'Tests\\Generator\\Semantics\\'], $class) . 'Generator';
        if (class_exists($generator) && !is_a($generator, Inhabitant::class, true)) {
            throw new LogicException($generator . ' must implement ' . Inhabitant::class . '.');
        }
        $value = is_a($generator, Inhabitant::class, true) ? (new $generator())->sample($choices) : (new Construction($choices, $catalogue, $metadata))->object($class, 6);
        if (!$value instanceof $class) {
            throw new LogicException($generator . ' did not generate ' . $class . '.');
        }
        return $value;
    }
}
