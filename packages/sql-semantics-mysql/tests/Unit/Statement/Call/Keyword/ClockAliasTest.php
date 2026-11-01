<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Keyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias;

#[CoversClass(ClockAlias::class)]
#[Medium]
final class ClockAliasTest extends TestCase
{
    public function testClockRetainsTheCanonicalOperation(): void
    {
        self::assertSame([Clock::CurrentDate, Clock::CurrentTime, Clock::Now, Clock::Now, Clock::Now], array_map(static fn (ClockAlias $alias): Clock => $alias->clock(), ClockAlias::cases()));
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
            foreach (ClockAlias::cases() as $alias) {
                foreach ([$alias->value, $alias->value . '()'] as $call) {
                    yield "$release->value/predicate/$call" => [$release, 'SELECT 1 FROM DUAL WHERE ' . $call . ' IS NULL'];
                    yield "$release->value/projection/$call" => [$release, 'SELECT ' . $call . ' AS chosen'];
                }
                if ($alias->clock()->precise()) {
                    yield "$release->value/precision/$alias->value" => [$release, 'SELECT ' . $alias->value . '(3) AS chosen'];
                    yield "$release->value/deferred/$alias->value" => [$release, 'CREATE PROCEDURE p() SELECT ' . $alias->value . '(263) AS chosen'];
                }
                if ($alias->clock() === Clock::Now) {
                    yield "$release->value/default/$alias->value" => [$release, 'CREATE TABLE t (v TIMESTAMP(3) DEFAULT ' . $alias->value . '(3) ON UPDATE ' . $alias->value . '(3))'];
                }
            }
            yield "$release->value/default/NOW" => [$release, 'CREATE TABLE t (v TIMESTAMP DEFAULT NOW())'];
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
