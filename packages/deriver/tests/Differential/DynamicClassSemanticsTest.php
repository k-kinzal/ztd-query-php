<?php

declare(strict_types=1);

namespace Tests\Differential;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\RuntimeOracle;

/**
 * Verifies runtime class operands and early errors against PHP 8.3.
 */
#[CoversNothing]
#[Medium]
final class DynamicClassSemanticsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(\Tests\Fake\Programs\DynamicClassPrograms::class, 'cases')]
    public function testRuntimeClassOperandsMatchTheTargetEngine(string $source, string $expected): void
    {
        $value = json_decode($expected, true, flags:JSON_THROW_ON_ERROR);
        self::assertSame($value, RuntimeOracle::evaluate($source)->native());
        $result = Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->projectDiagnostics);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($value, $result->normalOutcomes[0]->values['return']->native());
    }
}
