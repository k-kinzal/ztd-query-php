<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Query\Clause;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable as Model;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class ProgramVariableProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $value);
    }

    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
}
