<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\DestructuringPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Checks assignment patterns, reference cells, and target diagnostics.
 */
#[CoversNothing]
#[Medium]
final class DestructuringSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $normalJson Independently observed normal return values
     * @param string $exception Independently observed throwable class
     * @param bool $diagnostic Whether the target emits any diagnostic
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(DestructuringPrograms::class, 'cases')]
    public function testTargetDestructuringSemantics(string $source, string $normalJson, string $exception, bool $diagnostic): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
        $oracle = RuntimeOracle::observe($source);
        self::assertSame($exception, $oracle['exception']);
        self::assertSame($expected, $oracle['exception'] === '' ? [$oracle['value']->native()] : []);
        self::assertSame($diagnostic, $oracle['diagnostics'] !== []);
        $result = Analysis::returns($source);
        self::assertSame($expected, array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame($diagnostic, in_array('PHP_WARNING', array_column($result->frontiers, 'code'), true));
        self::assertSame([], array_diff(array_column($result->frontiers, 'code'), ['PHP_WARNING']));
    }

    /**
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(DestructuringPrograms::class, 'invalid')]
    public function testCompileTimePatternErrorsMatchTheTarget(string $source, string $message): void
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
