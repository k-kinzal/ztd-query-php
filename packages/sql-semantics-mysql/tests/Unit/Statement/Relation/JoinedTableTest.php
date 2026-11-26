<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(JoinedTable::class)]
#[Medium]
final class JoinedTableTest extends TestCase
{
    public function testDeriveRelationMergesUsingColumnsAndExtendsTheOuterSide(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->diagnose('SELECT a, t.a, u.a, c FROM t RIGHT JOIN u USING (a)', [$t, $u]);

        self::assertSame($u->columns[0], $operation->field(0)->column());
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $operation->field(3)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationMergesTheCommonColumnsOfANaturalJoin(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->diagnose('SELECT * FROM t NATURAL JOIN u', [$t, $u]);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveRelationReportsAUsingColumnAnOperandLacks(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->diagnose('SELECT 1 FROM t JOIN u USING (c)', [$t, $u]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveRelationNestsLikeTheParseOfTheRelease(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $modern = $semantics->diagnose('SELECT 1 FROM t JOIN u JOIN u AS v ON t.a = v.a', [$t, $u]);
        $legacy = (new Inspection('mysql-5.7.44'))->diagnose('SELECT 1 FROM t JOIN u JOIN u AS v ON t.a = v.a', []);

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
        $semantics = new Inspection();

        self::assertSame('SELECT 1 FROM t LEFT OUTER JOIN u ON t.a = u.a NATURAL RIGHT OUTER JOIN v CROSS JOIN w STRAIGHT_JOIN x INNER JOIN y USING (a, b)', $semantics->diagnose('select 1 from t left outer join u on t.a = u.a natural right outer join v cross join w straight_join x inner join y using (a, b)')->toString());
        self::assertSame('SELECT 1 FROM t NATURAL INNER JOIN u', $semantics->diagnose('select 1 from t natural inner join u')->toString());
        self::assertSame('SELECT 1 FROM t LEFT OUTER JOIN u USING (a)', (new Inspection('mysql-5.6.51'))->diagnose('select 1 from t left outer join u using (a)')->toString());
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
