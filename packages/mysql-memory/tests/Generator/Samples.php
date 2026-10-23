<?php

declare(strict_types=1);

namespace Tests\Generator;

use InvalidArgumentException;
use JsonException;

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
     * @param class-string $class
     * @param non-negative-int|null $sampleIndex One PHPUnit dataset, or null for the complete sequence
     * @return iterable<object>
     * @throws JsonException When a dependency inventory cannot be read
     * @throws InvalidArgumentException When the sample count is not positive
     */
    public function of(string $class, ?int $sampleIndex = null): iterable
    {
        $seed = getenv('MYSQL_MEMORY_PBT_SEED');
        $seed = $seed === false ? 'all-model-roots' : $seed;
        $samples = $sampleIndex === null ? $this->count() : $sampleIndex + 1;
        $catalogue = new Catalogue();
        if (self::$metadataClass !== $class) {
            self::$metadataClass = $class;
            self::$metadata = new Metadata();
        }
        $metadata = self::$metadata ??= new Metadata();
        $roots = $catalogue->roots();
        $index = array_search($class, $roots, true);
        for ($sample = $sampleIndex ?? 0; $sample < $samples; $sample++) {
            $choices = new Choices($index === false ? $seed . ':' . $class . ':' . $sample : $seed . ':' . ($index + $sample * count($roots)));
            $choices->one($roots);
            $generator = str_replace(['SqlSemantics\\Platform\\MySql\\', 'SqlSemantics\\'], ['Tests\\Generator\\MySql\\', 'Tests\\Generator\\Semantics\\'], $class);
            yield is_a($generator, Inhabitant::class, true) ? (new $generator())->sample($choices) : (new Construction($choices, $catalogue, $metadata))->object($class, 6);
        }
    }
}
