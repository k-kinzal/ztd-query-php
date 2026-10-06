<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;

#[CoversClass(MisuseRule::class)]
#[Medium]
final class MisuseRuleTest extends TestCase
{
    /**
     * @return array<string, array{string, MisuseRule}>
     */
    public static function providerStatements(): array
    {
        return [
            'star without tables' => ['SELECT *', MisuseRule::StarWithoutTables],
            'order by before compound' => ['SELECT 1 ORDER BY 1 UNION SELECT 2', MisuseRule::OrderByBeforeCompound],
            'limit before compound' => ['SELECT 1 LIMIT 1 UNION SELECT 2', MisuseRule::LimitBeforeCompound],
            'decorated column name' => ['WITH c(x DESC) AS (SELECT 1) SELECT * FROM c', MisuseRule::DecoratedColumnName],
            'duplicate common table' => ['WITH c AS (SELECT 1), c AS (SELECT 2) SELECT * FROM c', MisuseRule::DuplicateCommonTable],
            'circular reference' => ['WITH c AS (SELECT * FROM d), d AS (SELECT * FROM c) SELECT * FROM c', MisuseRule::CircularReference],
            'row value as result column' => ['SELECT (1, 2)', MisuseRule::TooManyValueColumns],
            'raise outside trigger' => ['SELECT RAISE(IGNORE)', MisuseRule::RaiseOutsideTrigger],
        ];
    }

    #[DataProvider('providerStatements')]
    public function testCasesAreReportedForTheStatementsThatBreakThem(string $sql, MisuseRule $rule): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze($sql);

        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame($rule, $query->facts->diagnostics[0]->rule);
        self::assertSame($rule->value, $query->facts->diagnostics[0]->message());
    }

    public function testCasesCarryTheWordsOfSqlite(): void
    {
        self::assertSame('ORDER BY clause should come after the compound operator not before', MisuseRule::OrderByBeforeCompound->value);
        self::assertSame('syntax error after column name', MisuseRule::DecoratedColumnName->value);
        self::assertSame(MisuseRule::StarWithoutTables, MisuseRule::from('no tables specified'));
        self::assertCount(16, MisuseRule::cases());
    }
}
