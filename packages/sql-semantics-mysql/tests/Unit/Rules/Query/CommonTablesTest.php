<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Resolution\CommonBinding;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Query\CommonTables;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CommonTables::class)]
#[Medium]
final class CommonTablesTest extends TestCase
{
    public function testBindSeesEarlierTablesOnly(): void
    {
        $operation = (new Inspection())->diagnose('WITH a AS (SELECT 1 AS x), b AS (SELECT x FROM a) SELECT x FROM b', []);
        $forward = (new Inspection())->diagnose('WITH b AS (SELECT x FROM a), a AS (SELECT 1 AS x) SELECT 1', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Known::class, $operation->field('x')->type);
        self::assertInstanceOf(MissingTable::class, $forward->facts->diagnostics[0]);
    }

    public function testExtendedAddsTablesWithoutAQueryLevel(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $outer = $derivation->environment();
        $extended = (new CommonTables())->extended($outer, [new CommonBinding(new Name('c'), new Dual(), new RowShape([]))]);

        self::assertSame($outer->outer, $extended->outer);
        self::assertNotNull($extended->commonTable(new Name('c')));
    }

    public function testPendingFindsTheRecursiveTableARightOperandRefersTo(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $pending = new CommonBinding(new Name('c'), new Dual(), new RowShape([], [new RecursiveReference(new Name('c'))]));
        $scope = (new CommonTables())->extended($derivation->environment(), [$pending]);
        $refers = $semantics->diagnose('SELECT 1 FROM c')->statement;
        $other = $semantics->diagnose('SELECT 1 FROM d')->statement;

        self::assertInstanceOf(Select::class, $refers);
        self::assertInstanceOf(Select::class, $other);
        self::assertSame($pending, (new CommonTables())->pending($scope, $refers, $derivation));
        self::assertNull((new CommonTables())->pending($scope, $other, $derivation));
    }

    public function testAnchoredBindsTheColumnsOfTheNonrecursivePart(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $pending = new CommonBinding(new Name('c'), new Dual(), new RowShape([], [new RecursiveReference(new Name('c'))]));
        $scope = (new CommonTables())->extended($derivation->environment(), [$pending]);
        $anchor = new QueryFact([new Field(0, new OutputSlot(new Name('n'), new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull))], $semantics->context()->columnNames);
        $binding = (new CommonTables())->anchored($scope, $pending, $anchor, $derivation)->commonTable(new Name('c'));

        self::assertNotNull($binding);
        self::assertSame('n', $binding->shape->slots[0]->name?->value);
        self::assertSame(Nullability::Nullable, $binding->shape->slots[0]->nullability);
        self::assertTrue($binding->shape->complete());
    }

    public function testShapeRenamesTheColumnsByTheColumnList(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $table = new CommonTableExpression(new Name('c'), [new Name('x')], new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $anchor = new QueryFact([new Field(0, new OutputSlot(new Name('n'), new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull))], $semantics->context()->columnNames);
        $shape = (new CommonTables())->shape(new CommonBinding(new Name('c'), $table, new RowShape([])), $anchor, $derivation);

        self::assertSame('x', $shape->slots[0]->name?->value);
    }

    public function testNullableMakesEveryColumnNullable(): void
    {
        $semantics = new Inspection();
        $anchor = new QueryFact([new Field(0, new OutputSlot(new Name('n'), new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull))], $semantics->context()->columnNames);

        self::assertSame(Nullability::Nullable, (new CommonTables())->nullable($anchor)->fields()?->at(0)->nullability);
    }

    public function testOperandsAnswersTheChainInWrittenOrder(): void
    {
        $query = (new Inspection())->diagnose('(SELECT 1 UNION SELECT 2 UNION SELECT 3)')->statement;

        self::assertInstanceOf(ParenthesizedQuery::class, $query);
        self::assertCount(3, (new CommonTables())->operands($query));
        self::assertInstanceOf(SetOperation::class, $query->query);
        self::assertSame([$query->query->right], array_slice((new CommonTables())->operands($query->query), 2));
        self::assertSame([$query->query->right], (new CommonTables())->operands($query->query->right));
    }

    public function testRefersFindsAnUnqualifiedReference(): void
    {
        $semantics = new Inspection();
        $derivation = new Derivation($semantics->context());
        $query = $semantics->diagnose('SELECT 1 FROM db.c, (SELECT 1 FROM c) AS d')->statement;
        $qualified = $semantics->diagnose('SELECT 1 FROM db.c')->statement;

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(Select::class, $qualified);
        self::assertTrue((new CommonTables())->refers($query, new Name('c'), $derivation));
        self::assertFalse((new CommonTables())->refers($qualified, new Name('c'), $derivation));
    }

    public function testOperandsWalksALeadingUnion(): void
    {
        $query = (new Inspection('mysql-5.6.51'))->diagnose('SELECT 1 UNION SELECT 2 LIMIT 1 UNION SELECT 3')->statement;

        self::assertInstanceOf(SetOperation::class, $query);
        self::assertCount(3, (new CommonTables())->operands($query));
    }

    public function testShapeKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Inspection())->diagnose("WITH RECURSIVE c AS (SELECT 'é' UNION ALL SELECT x FROM c) SELECT 1");

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testNullableKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Inspection())->diagnose("WITH RECURSIVE c AS (SELECT 'é' UNION ALL SELECT 1 FROM c) SELECT * FROM c");

        self::assertEquals([new SessionState('character_set_client')], $operation->field(0)->slot->unnamed);
    }
}
