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
use Tests\Fake\Programs\ArrayMutationPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Compares array_shift, array_pop, array_push, array_unshift and key selection with PHP 8.3.
 */
#[CoversNothing]
#[Medium]
final class ArrayMutationSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $normalJson Independently observed normal return values
     * @param string $exception Independently observed throwable class
     * @throws JsonException If fixture observations cannot be decoded
     * @throws RuntimeException If the PHP 8.3 oracle is unavailable
     */
    #[DataProviderExternal(ArrayMutationPrograms::class, 'cases')]
    public function testArrayMutationsMatchTheRuntime(string $source, string $normalJson, string $exception): void
    {
        $expected = json_decode($normalJson, true, 512, JSON_THROW_ON_ERROR);
        $oracle = RuntimeOracle::observe($source);
        self::assertSame($exception, $oracle['exception']);
        self::assertSame($expected, $oracle['exception'] === '' ? [$oracle['value']->native()] : []);
        self::assertSame([], $oracle['diagnostics']);
        $result = Analysis::returns($source);
        self::assertSame($expected, array_map(static fn (Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
        self::assertSame($exception === '' ? [] : [$exception], array_map(static fn (Exceptional $outcome): int|float|string|bool|null => $outcome->exception->literal, $result->exceptionalOutcomes));
        self::assertSame([], $result->frontiers);
    }
}
