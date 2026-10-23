<?php

declare(strict_types=1);

namespace Tests\Property;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Generator\Samples;

/**
 * Samples contextual components and analysis results through their public constructors.
 *
 * @phpstan-require-extends TestCase
 */
trait SemanticValueProperty
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }

    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        foreach ((new Samples())->of(self::MODEL, $sample) as $value) {
            self::assertInstanceOf(self::MODEL, $value);
        }
    }
}
