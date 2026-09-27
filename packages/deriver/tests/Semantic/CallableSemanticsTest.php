<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\CallablePrograms;

/**
 * Checks callable values, diagnostics, and mutations retained before exceptions.
 */
#[CoversNothing]
#[Small]
final class CallableSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $normalJson Independently observed normal return values
     * @param string $exception Independently observed throwable class
     * @param bool $diagnostic Whether the target emits any diagnostic
     * @throws JsonException If fixture observations cannot be decoded
     */
    #[DataProviderExternal(CallablePrograms::class, 'cases')]
    public function testTargetCallableSemantics(string $source, string $normalJson, string $exception, bool $diagnostic): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
        $result = Analysis::returns($source);
        self::assertSame($expected, array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame($diagnostic, in_array('PHP_WARNING', array_column($result->frontiers, 'code'), true));
        self::assertSame([], array_diff(array_column($result->frontiers, 'code'), ['PHP_WARNING']));
    }
}
