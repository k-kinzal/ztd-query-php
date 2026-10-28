<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

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
use SqlSemantics\Platform\MySql\Statement\Table\TableWord;

#[CoversClass(TableWord::class)]
#[Medium]
final class TableWordTest extends TestCase
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
            foreach (['TABLE', 'TABLES'] as $word) {
                foreach ([
                    "ANALYZE LOCAL $word t",
                    "OPTIMIZE $word t",
                    "REPAIR NO_WRITE_TO_BINLOG $word t QUICK",
                    "CHECK $word t EXTENDED",
                    "CHECKSUM $word t",
                    "FLUSH LOCAL $word t WITH READ LOCK",
                    "LOCK $word t READ",
                    "UNLOCK $word",
                    "DROP TEMPORARY $word IF EXISTS t",
                    "RENAME $word t TO u",
                ] as $sql) {
                    yield "$release->value: $sql" => [$release, $sql];
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
