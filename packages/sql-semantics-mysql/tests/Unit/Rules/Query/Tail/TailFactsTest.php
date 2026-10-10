<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Tail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\Tail\TailFacts;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Type\Dependent;

#[CoversClass(TailFacts::class)]
#[Medium]
final class TailFactsTest extends TestCase
{
    public function testDeriveReportsAnUnknownLockedTable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t AS u FOR UPDATE OF d.t');

        self::assertEquals([new Misuse(MisuseRule::UnknownLockedTable, new QualifiedName(new Name('t'), new Name('d')))], $operation->facts->diagnostics);
    }

    public function testDeriveChecksTheLockingClausesOfAQueryStatement(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('(SELECT a FROM t LOCK IN SHARE MODE) FOR UPDATE');

        self::assertEquals([new Misuse(MisuseRule::RepeatedLockedTable, new Name('t'))], $operation->facts->diagnostics);
    }

    public function testDeriveWarnsAboutIntoBeforeTheLockingClauses(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t LIMIT 1 INTO @x FOR SHARE');

        self::assertEquals([new Deprecation(Deprecated::IntoInsideQuery)], $operation->facts->warnings);
    }

    public function testDeriveWarnsAboutIntoAtTheEndOfASetOperationBeforeTheLockingClauses(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION SELECT 2 INTO @x FOR SHARE');

        self::assertEquals([new Deprecation(Deprecated::IntoInsideQuery)], $operation->facts->warnings);
    }

    public function testDeriveLeavesIntoAfterTheSelectListOfABlockAlone(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 INTO @x FOR SHARE');

        self::assertSame([], $operation->facts->warnings);
    }

    public function testOperandWarnsAboutIntoInsideTheLastOperandOfASetOperation(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION (SELECT 2 INTO @x)');

        self::assertEquals([new Deprecation(Deprecated::IntoInsideQuery)], $operation->facts->warnings);
    }

    public function testOperandWarnsAboutIntoBeforeTheFromClauseOfTheLastOperand(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION SELECT a INTO @x FROM t');

        self::assertEquals([new Deprecation(Deprecated::IntoInsideQuery)], $operation->facts->warnings);
    }

    public function testOperandLeavesIntoAtTheEndOfASetOperationAlone(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 UNION SELECT 2 INTO @x');

        self::assertSame([], $operation->facts->warnings);
    }

    public function testTrailingAnswersTheLastBlockOfASetOperation(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('(SELECT 1 UNION SELECT 2) FOR SHARE');

        self::assertInstanceOf(QueryStatement::class, $operation->statement);
        self::assertInstanceOf(Select::class, (new TailFacts())->trailing($operation->statement->query));
    }

    public function testLimitReportsAnUndeclaredVariable(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t LIMIT n');

        self::assertEquals([new UndeclaredVariable(new Name('n'))], $operation->facts->diagnostics);
    }

    public function testLimitDerivesTheOperands(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t LIMIT ?, ?');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(RowLimit::class, $operation->statement->limit);
        self::assertInstanceOf(Dependent::class, $operation->facts->scalar($operation->statement->limit->count)->type);
    }

    public function testDeriveWarnsOfTheCharacterSetIntoOutfileNames(): void
    {
        $facts = (new Semantics(Dialect::MySql))->analyze("SELECT 1 INTO OUTFILE 'f' CHARACTER SET utf8mb3")->facts;

        self::assertSame([Deprecated::Utf8mb3->value], array_map(static fn ($warning): string => $warning->message(), $facts->warnings));
    }

    public function testLimitReportsTheOffsetWrittenBeforeACommaFirstInMySql57(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 LIMIT a, b');

        self::assertEquals(new UndeclaredVariable(new Name('a')), $operation->facts->diagnostics[0]);
    }

    public function testLimitReportsTheOffsetWrittenBeforeACommaFirstInMySql84(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('SELECT 1 LIMIT a, b');

        self::assertEquals([new UndeclaredVariable(new Name('a')), new UndeclaredVariable(new Name('b'))], $operation->facts->diagnostics);
    }

    public function testLimitReportsTheCountFirstWhenTheOffsetIsWrittenAfterIt(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-9.1.0'))->analyze('SELECT 1 LIMIT b OFFSET a');

        self::assertEquals([new UndeclaredVariable(new Name('b')), new UndeclaredVariable(new Name('a'))], $operation->facts->diagnostics);
    }
}
