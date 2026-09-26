<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\ConversionPrograms;

/**
 * Verifies explicit conversion values, side effects, and exception state.
 */
#[CoversNothing]
#[Medium]
final class ConversionSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted fixture source
     * @param Term $expected Expected observation
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(ConversionPrograms::class, 'cases')]
    public function testConversionPreservesItsObservableBehavior(string $source, Term $expected): void
    {
        $result = Analysis::returns($source);
        self::assertSame([], $result->frontiers, $source);
        self::assertSame([], $result->exceptionalOutcomes, $source);
        self::assertCount(1, $result->normalOutcomes, $source);
        self::assertSame($expected->native(), $result->normalOutcomes[0]->values['return']->native(), $source);
    }
}
