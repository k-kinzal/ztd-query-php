<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Style;
use SqlFormatter\Facade\Formatter;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\BinlogOption;

#[CoversClass(BinlogOption::class)]
#[Medium]
final class BinlogOptionTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['', 'LOCAL', 'NO_WRITE_TO_BINLOG'] as $word) {
                foreach ([
                    "ANALYZE $word TABLE t",
                    "OPTIMIZE $word TABLE t",
                    "REPAIR $word TABLE t QUICK",
                    "FLUSH $word STATUS",
                    "FLUSH $word TABLES t",
                    "ALTER TABLE t ADD PARTITION $word PARTITIONS 2",
                    "ALTER TABLE t COALESCE PARTITION $word 1",
                    "ALTER TABLE t REORGANIZE PARTITION $word p INTO (PARTITION q)",
                    "ALTER TABLE t ANALYZE PARTITION $word ALL",
                    "ALTER TABLE t REPAIR PARTITION $word ALL QUICK",
                    "ALTER TABLE t REBUILD PARTITION $word ALL",
                    "ALTER TABLE t OPTIMIZE PARTITION $word ALL",
                ] as $sql) {
                    yield "$release->value: $sql" => [$release, $sql];
                }
                if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                    foreach (['', 'LOCAL', 'NO_WRITE_TO_BINLOG'] as $trailing) {
                        $sql = "ALTER TABLE t OPTIMIZE PARTITION $word ALL $trailing";
                        yield "$release->value: trailing/$sql" => [$release, $sql];
                    }
                }
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testRetainsTheChosenKeywordAcrossStatementRoles(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new Formatter($semantics->parser(), new FormatOptions(Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format((new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), $semantics->analyze($sql))->toString()));
    }
}
