<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\SetFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SetFacts::class)]
#[Medium]
final class SetFactsTest extends TestCase
{
    public function testDeriveSeesARecursiveTableWithTheColumnsOfItsAnchor(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("WITH RECURSIVE c AS (SELECT 1 AS n UNION ALL SELECT CONCAT(n, 'x') FROM c) SELECT n FROM c", []);
        $type = $operation->field('n')->type;

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('BIGINT', $type->descriptor->name());
        self::assertSame(Nullability::Nullable, $operation->field('n')->nullability);
    }

    public function testOperandsCombinesTheColumnsOfBothOperands(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL)');
        $operation = $semantics->analyze('SELECT * FROM (SELECT a AS x FROM t UNION SELECT NULL FROM DUAL LIMIT 1 ORDER BY x) AS d', [$table]);
        $type = $operation->field('x')->type;

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Known::class, $type);
        self::assertSame('INT', $type->descriptor->name());
        self::assertSame(Nullability::Nullable, $operation->field('x')->nullability);
    }

    public function testLeadingCombinesTheOperandsBeforeALaterUnion(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.6.51');
        $table = $semantics->analyze('CREATE TABLE t (a INT NOT NULL)');
        $operation = $semantics->analyze('SELECT a AS x FROM t UNION SELECT NULL LIMIT 1 UNION SELECT 2', [$table]);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame(Nullability::Nullable, $operation->field('x')->nullability);
        self::assertSame('BIGINT', $operation->field('x')->type instanceof Known ? $operation->field('x')->type->descriptor->name() : null);
    }

    public function testLeadingRefusesAnOwnLimitOnMySql57(): void
    {
        $limited = new Select([], [new SelectExpression(new NumberLiteral('2'))], null, null, null, null, [], null, [], new RowLimit(new NumberLiteral('1')));
        $union = new SetOperation(new LeadingUnion(new Select([], [new SelectExpression(new NumberLiteral('1'))]), null, $limited), SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('3'))]));

        $this->expectExceptionMessage('A SELECT that keeps its own LIMIT before a later UNION needs MySQL 5.6.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]), $union);
    }
}
