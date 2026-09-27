<?php

declare(strict_types=1);

namespace Tests\Differential;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;
use Tests\Fake\Programs\IncrementPrograms;
use Tests\Fake\RuntimeOracle;

/**
 * Checks scalar updates, post-increment observations and unchanged exceptional state.
 */
#[CoversNothing]
#[Medium]
final class IncrementSemanticsTest extends TestCase
{
    /**
     * @param string $source Trusted PHP fixture
     * @param string $expectedJson Independently recorded target result
     * @param bool $diagnostic Whether the target emits a warning or deprecation
     * @throws JsonException If fixture observations cannot be encoded
     */
    #[DataProviderExternal(IncrementPrograms::class, 'cases')]
    public function testTargetMutationSemantics(string $source, string $expectedJson, bool $diagnostic): void
    {
        $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        $oracle = RuntimeOracle::observe($source);
        self::assertSame('', $oracle['exception']);
        self::assertSame($expected, $oracle['value']->native());
        self::assertSame($diagnostic, $oracle['diagnostics'] !== []);
        $result = Analysis::returns($source);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame($expected, $result->normalOutcomes[0]->values['return']->native());
        self::assertSame($diagnostic ? ['PHP_WARNING'] : [], array_column($result->frontiers, 'code'));
    }
}
