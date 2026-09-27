<?php

declare(strict_types=1);

namespace Tests\Differential;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\ClassNamePrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Verifies class-name values and lexical-scope errors against PHP 8.3.
 */
#[CoversNothing]
#[Medium]
final class ClassNameSemanticsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(ClassNamePrograms::class, 'cases')]
    public function testClassNamesMatchTheTargetEngine(string $source, string $expected): void
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

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(ClassNamePrograms::class, 'invalid')]
    public function testInvalidLexicalScopesAreRejectedBeforeAnyCallableRuns(string $source, string $message): void
    {
        $result = Analysis::returns($source);
        self::assertCount(1, $result->projectDiagnostics);
        self::assertSame('INCOMPLETE_SOURCE', $result->projectDiagnostics[0]->code);
        self::assertStringContainsString($message, $result->projectDiagnostics[0]->operation);
        self::assertSame('open', $result->assessment->closure);
        self::assertArrayNotHasKey('return', $result->normalOutcomes[0]->values);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);
        RuntimeOracle::evaluate($source);
    }
}
