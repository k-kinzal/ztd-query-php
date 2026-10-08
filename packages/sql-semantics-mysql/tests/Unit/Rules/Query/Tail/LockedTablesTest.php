<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Tail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\LockedTables;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockingClause;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\RowShape;

#[CoversClass(LockedTables::class)]
#[Medium]
final class LockedTablesTest extends TestCase
{
    public function testCheckReportsATableLockedTwice(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('TABLE t2 LOCK IN SHARE MODE LOCK IN SHARE MODE');

        self::assertEquals([new Misuse(MisuseRule::RepeatedLockedTable, new Name('t2'))], $operation->facts->diagnostics);
    }

    public function testCheckNamesATableOfAnOfListAsWritten(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM t1 AS x FOR SHARE OF x FOR SHARE OF x');

        self::assertEquals([new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('x')))], $operation->facts->diagnostics);
    }

    public function testCheckLocksTheTablesOfTheLastOperandOfASetOperation(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('TABLE t2 EXCEPT TABLE t1 FOR SHARE OF t2');

        self::assertEquals([new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t2')))], $operation->facts->diagnostics);
    }

    public function testCheckLeavesDistinctTablesAlone(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM t1, t2 FOR SHARE OF t1 FOR UPDATE OF t2');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testCheckCountsAJsonTableAsLocked(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT * FROM JSON_TABLE('[1]', '$[*]' COLUMNS (x INT PATH '$')) AS j FOR SHARE");

        self::assertEquals([new Misuse(MisuseRule::RepeatedLockedTable, new Name('j'))], $operation->facts->diagnostics);
    }

    public function testCheckLeavesDerivedTablesAndCommonTablesOutOfAClauseWithoutOf(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1) SELECT * FROM c, (SELECT 1) AS d FOR SHARE FOR SHARE');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testCheckCountsADerivedTableNamedByOfAsLocked(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM (SELECT 1) AS d FOR SHARE OF d');

        self::assertEquals([new Misuse(MisuseRule::RepeatedLockedTable, new QualifiedName(new Name('d')))], $operation->facts->diagnostics);
    }

    public function testCheckCountsTheTablesOfAHighPriorityQueryAsLocked(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT HIGH_PRIORITY a FROM t1 UNION SELECT a FROM t2 FOR SHARE');

        self::assertEquals([new Misuse(MisuseRule::RepeatedLockedTable, new Name('t2'))], $operation->facts->diagnostics);
    }

    public function testApplyLocksTheTablesOfAClauseWithoutOf(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $relation = new VisibleRelation(new TableReference(new QualifiedName(new Name('t'))), new RowShape([]), null, new QualifiedName(new Name('t')));

        self::assertSame([[[$relation, true, true]], null], (new LockedTables())->apply($derivation, new LockingClause(LockStrength::Share), [[$relation, true, false]]));
    }

    public function testChainAnswersTheBlockAndTheClausesInTheOrderTheyApply(): void
    {
        $inner = new LockingClause(LockStrength::Share);
        $outer = new LockingClause(LockStrength::Update);
        $table = new ExplicitTable(new QualifiedName(new Name('t')));

        self::assertSame([$table, [$inner, $outer], []], (new LockedTables())->chain(new QueryStatement(new ParenthesizedQuery(new QueryStatement($table, [$inner])), [$outer])));
    }

    public function testLeadingAnswersTheFirstBlockOfAQuery(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT HIGH_PRIORITY 1 UNION SELECT 2 FOR SHARE');

        self::assertInstanceOf(QueryStatement::class, $operation->statement);
        self::assertSame([SelectOption::HighPriority], (new LockedTables())->leading($operation->statement)?->options);
    }

    public function testEntriesAnswersTheTablesOfTheBlock(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $entries = (new LockedTables())->entries($derivation, $derivation->environment(), new ExplicitTable(new QualifiedName(new Name('t'))), [], []);

        self::assertSame([true, false], [$entries[0][1], $entries[0][2]]);
    }

    public function testDefinesFindsACommonTableOfTheQuery(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());

        self::assertTrue((new LockedTables())->defines($derivation, [new Name('c')], new Name('c')));
    }

    public function testTermsAnswersTheTablesOfAFromClauseInOrder(): void
    {
        $first = new TableReference(new QualifiedName(new Name('a')));
        $second = new TableReference(new QualifiedName(new Name('b')));

        self::assertSame([$first, $second], (new LockedTables())->terms(new TableList([$first, $second])));
    }
}
