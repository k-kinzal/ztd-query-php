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
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableList::class)]
#[Medium]
final class TableListTest extends TestCase
{
    public function testDeriveRelationJoinsTheMembersLeftToRight(): void
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
        $operation = $semantics->diagnose('SELECT * FROM t, u', [$t, $u]);

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(TableList::class, $operation->statement->from);
        self::assertCount(4, $operation->facts->relation($operation->statement->from)->shape->slots);
        self::assertSame(['a', 'b', 'a', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testDeriveRelationKeepsTheJoinConditionToItsOperands(): void
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
        $operation = $semantics->diagnose('SELECT 1 FROM t, u JOIN u AS v ON t.a = v.a', [$t, $u]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(MissingColumn::class, $operation->facts->diagnostics[0]);
    }

    public function testRenderWritesTheMembers(): void
    {
        self::assertSame('SELECT 1 FROM t, u, v', (new Inspection())->diagnose('select 1 from t,u,v')->toString());
    }

    public function testAListOfOneMemberIsRejected(): void
    {
        $this->expectExceptionMessage('A comma list joins at least two table references.');

        new TableList([new Dual()]);
    }

    public function testANestedListIsRejected(): void
    {
        $this->expectExceptionMessage('A comma list used as a member is written in parentheses.');

        new TableList([new Dual(), new TableList([new Dual(), new Dual()])]);
    }
}
