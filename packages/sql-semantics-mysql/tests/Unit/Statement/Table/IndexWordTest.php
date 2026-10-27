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
use SqlSemantics\Platform\MySql\Statement\Table\IndexWord;

#[CoversClass(IndexWord::class)]
#[Medium]
final class IndexWordTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, string}>
     */
    public static function providerIndexStatements(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['KEY', 'INDEX'] as $word) {
                foreach ([
                    "CREATE TABLE t(a INT, $word i(a))",
                    "CREATE TABLE t(a INT, UNIQUE $word i(a))",
                    "CREATE TABLE t(a TEXT, FULLTEXT $word i(a))",
                    "CREATE TABLE t(a POINT, SPATIAL $word i(a))",
                    "ALTER TABLE t ADD $word i(a)",
                    "ALTER TABLE t DROP $word i",
                    "SELECT a FROM t USE $word ()",
                    "SELECT a FROM t FORCE $word FOR JOIN (i)",
                    "SELECT a FROM t IGNORE $word FOR ORDER BY (PRIMARY,i)",
                    "CACHE INDEX t $word () IN DEFAULT",
                    "CACHE INDEX t PARTITION(ALL) $word (PRIMARY,PRIMARY) IN DEFAULT",
                    "LOAD INDEX INTO CACHE t $word (i) IGNORE LEAVES",
                    "LOAD INDEX INTO CACHE t PARTITION(ALL) $word ()",
                ] as $sql) {
                    yield "$release->value: $sql" => [$release, $sql];
                }
                if ($release !== GrammarRelease::MySql5651) {
                    $sql = "ALTER TABLE t RENAME $word i TO j";
                    yield "$release->value: $sql" => [$release, $sql];
                }
            }
        }
    }

    #[DataProvider('providerIndexStatements')]
    public function testRetainsTheChosenKeywordAcrossIndexRoles(GrammarRelease $release, string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $formatter = new Formatter($semantics->parser(), new FormatOptions(Style::Compact));
        self::assertSame($formatter->format($sql), $formatter->format($semantics->analyze($sql)->toString()));
    }
}
