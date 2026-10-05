<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JoinedTable::class)]
#[Medium]
final class JoinedTableTest extends TestCase
{
    public function testDeriveRelationMergesUsingColumnsAndExtendsTheOuterSide(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT a, t.a, u.a, c FROM t RIGHT JOIN u USING (a)', [$t, $u]);

        self::assertSame($u->columns[0], $operation->field(0)->column());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(3)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationMergesTheCommonColumnsOfANaturalJoin(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT * FROM t NATURAL JOIN u', [$t, $u]);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationReportsAUsingColumnAnOperandLacks(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT 1 FROM t JOIN u USING (c)', [$t, $u]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveRelationNestsLikeTheParseOfTheRelease(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $modern = $semantics->analyze('SELECT 1 FROM t JOIN u JOIN u AS v ON t.a = v.a', [$t, $u]);
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM t JOIN u JOIN u AS v ON t.a = v.a', []);

        self::assertInstanceOf(Select::class, $modern->statement);
        self::assertInstanceOf(JoinedTable::class, $modern->statement->from);
        self::assertInstanceOf(JoinedTable::class, $modern->statement->from->right);
        self::assertCount(1, $modern->facts->diagnostics);
        self::assertInstanceOf(Select::class, $legacy->statement);
        self::assertInstanceOf(JoinedTable::class, $legacy->statement->from);
        self::assertInstanceOf(JoinedTable::class, $legacy->statement->from->left);
    }

    public function testRenderWritesTheOperatorAndTheCondition(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame('SELECT 1 FROM t LEFT OUTER JOIN u ON t.a = u.a NATURAL RIGHT OUTER JOIN v CROSS JOIN w STRAIGHT_JOIN x INNER JOIN y USING (a, b)', $semantics->analyze('select 1 from t left outer join u on t.a = u.a natural right outer join v cross join w straight_join x inner join y using (a, b)')->toString());
        self::assertSame('SELECT 1 FROM t NATURAL INNER JOIN u', $semantics->analyze('select 1 from t natural inner join u')->toString());
        self::assertSame('SELECT 1 FROM t LEFT OUTER JOIN u USING (a)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t left outer join u using (a)')->toString());
    }

    public function testAConditionOnANaturalJoinIsRejected(): void
    {
        $this->expectExceptionMessage('A natural join takes no condition.');

        new JoinedTable(new Dual(), JoinOperator::Natural, new Dual(), null, [new Name('a')]);
    }

    public function testBothConditionsAreRejected(): void
    {
        $this->expectExceptionMessage('A join has an ON condition or a USING list, not both.');

        new JoinedTable(new Dual(), JoinOperator::Join, new Dual(), new NumberLiteral('1'), [new Name('a')]);
    }

    public function testAJoinOnTheRightOfANaturalJoinIsRejected(): void
    {
        $this->expectExceptionMessage('The right operand of a natural join is no unparenthesized join.');

        new JoinedTable(new Dual(), JoinOperator::Natural, new JoinedTable(new Dual(), JoinOperator::Cross, new Dual()));
    }

    public function testACommaListAsAnOperandIsRejected(): void
    {
        $this->expectExceptionMessage('A comma list joined to a table is written in parentheses.');

        new JoinedTable(new TableList([new Dual(), new Dual()]), JoinOperator::Cross, new Dual());
    }
}
