<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TableRule;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TableRule::class)]
#[Medium]
final class TableRuleTest extends TestCase
{
    public function testTableLowersEveryPart(): void
    {
        self::assertSame('SELECT a FROM db.t PARTITION (p) AS x USE INDEX (i) TABLESAMPLE SYSTEM (5)', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select a from db.t partition (p) as x use index (i) tablesample system (5)')->toString());
        self::assertSame('SELECT a FROM t PARTITION (p) x', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t partition (p) x')->toString());
    }

    public function testPartitionsLowersTheNames(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t PARTITION (p0, p1)');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableReference::class, $operation->statement->from);
        self::assertSame(['p0', 'p1'], array_map(static fn (Name $name): string => $name->value, $operation->statement->from->partitions));
    }

    public function testNamesLowersTheUsingList(): void
    {
        self::assertSame('SELECT 1 FROM t JOIN u USING (a, b)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from t join u using (a, b)')->toString());
    }

    public function testColumnsLowersTheColumnList(): void
    {
        self::assertSame('SELECT 1 FROM (SELECT 1, 2) d (x, y)', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('select 1 from (select 1, 2) d (x, y)')->toString());
    }

    public function testAliasLowersEveryAliasForm(): void
    {
        self::assertSame('SELECT 1 FROM t = x, u AS y, v z', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t = x, u as y, v z')->toString());
        self::assertSame('SELECT 1 FROM t x', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from t x')->toString());
    }

    public function testHintsLowersTheHintsInOrder(): void
    {
        self::assertSame('SELECT 1 FROM t IGNORE INDEX (i) FORCE INDEX (j)', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from t ignore index (i) force index (j)')->toString());
    }

    public function testHintLowersOneHint(): void
    {
        self::assertSame('SELECT 1 FROM t USE INDEX FOR JOIN ()', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from t use key for join ()')->toString());
    }

    public function testIndexesLowersNamesAndPrimary(): void
    {
        self::assertSame('SELECT 1 FROM t FORCE INDEX (PRIMARY, i)', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from t force index (primary, i)')->toString());
    }

    public function testSampleLowersEveryPercentage(): void
    {
        self::assertSame('SELECT 1 FROM t TABLESAMPLE BERNOULLI (?)', (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('select 1 from t tablesample bernoulli (?)')->toString());
    }

    public function testMarkAnswersWhatIsWrittenBeforeTheAlias(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t = x, u as y, v z, .w');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableList::class, $operation->statement->from);
        self::assertSame([AliasMark::Equals, AliasMark::As, AliasMark::Bare, AliasMark::As], array_map(static fn (object $table): AliasMark => $table instanceof TableReference ? $table->mark : AliasMark::As, $operation->statement->from->members));
        self::assertSame('SELECT 1 FROM t = x, u AS y, v z, .w', $operation->toString());
    }
}
