<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateAlias;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

#[CoversClass(AggregateAlias::class)]
#[Medium]
final class AggregateAliasTest extends TestCase
{
    public function testFunctionRetainsTheCanonicalOperation(): void
    {
        self::assertSame([AggregateFunction::StandardDeviation, AggregateFunction::StandardDeviation, AggregateFunction::Variance], array_map(static fn (AggregateAlias $alias): AggregateFunction => $alias->function(), AggregateAlias::cases()));
    }

    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerQueries(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (AggregateAlias::cases() as $alias) {
                yield "$release->value/projection/$alias->value" => [$release, 'SELECT ' . $alias->value . '(1) AS chosen'];
                yield "$release->value/deferred/$alias->value" => [$release, 'CREATE PROCEDURE p() SELECT ' . $alias->value . '(ROW(1, 2)) AS chosen'];
                if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                    yield "$release->value/window/$alias->value" => [$release, 'SELECT ' . $alias->value . '(1) OVER () AS chosen'];
                    yield "$release->value/frame/$alias->value" => [$release, 'CREATE PROCEDURE p() SELECT ' . $alias->value . '(1) OVER (GROUPS CURRENT ROW) AS chosen'];
                }
            }
        }
    }

    #[DataProvider('providerQueries')]
    public function testRenderRetainsAliasesThroughPublication(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new \SqlFormatter\Facade\Formatter($semantics->parser(), new \SqlFormatter\Core\FormatOptions(\SqlFormatter\Core\Style::Compact));
        $published = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql));
        self::assertSame($formatter->format($sql), $formatter->format($published->toString()));
    }
}
