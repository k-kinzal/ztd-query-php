<?php

declare(strict_types=1);

namespace Tests\Differential;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\ExceptionPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Compares throw validation, identity and previous-exception chains with PHP 8.3.
 */
#[CoversNothing]
#[Medium]
final class ExceptionSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $expectedJson Independently recorded result
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(ExceptionPrograms::class, 'cases')]
    public function testExceptionValidationAndChainingMatchTheRuntime(string $source, string $expectedJson): void
    {
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, RuntimeOracle::evaluate($source)->native());
        $result = Analysis::returns($source);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
    }
}
