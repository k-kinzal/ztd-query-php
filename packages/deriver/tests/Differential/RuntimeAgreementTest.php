<?php

declare(strict_types=1);

namespace Tests\Differential;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\GeneratedPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Compares independently generated fixtures with the real target PHP engine.
 */
#[CoversNothing]
#[Group('differential')]
#[Medium]
final class RuntimeAgreementTest extends TestCase
{
    /**
     * @param string $source
     * @throws JsonException If captured metadata cannot be encoded
     */
    #[DataProviderExternal(GeneratedPrograms::class, 'cases')]
    public function testConcreteExecutionAgreesWithTheTargetRuntime(string $source): void
    {
        $expected = RuntimeOracle::evaluate($source);
        $result = Analysis::returns($source);
        self::assertSame([], $result->frontiers, $source);
        self::assertSame([], $result->exceptionalOutcomes, $source);
        self::assertCount(1, $result->normalOutcomes, $source);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native(), $source);
    }
}
