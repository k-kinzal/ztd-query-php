<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\CommonTables;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as DomainField;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(CommonTables::class)]
#[Medium]
final class CommonTablesTest extends TestCase
{
    public function testBindSeesEarlierTablesOnly(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH a AS (SELECT 1 AS x), b AS (SELECT x FROM a) SELECT x FROM b', []);
        $forward = (new Semantics(Dialect::MySql))->analyze('WITH b AS (SELECT x FROM a), a AS (SELECT 1 AS x) SELECT * FROM b', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(Known::class, $operation->field('x')->type);
        self::assertInstanceOf(MissingTable::class, $forward->facts->diagnostics[0]);
    }

    public function testBindReportsTheProblemsOfAnExpressionOnlyWhereItIsUsed(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $unused = $semantics->analyze('WITH b AS (SELECT x FROM a), c (p, q) AS (SELECT 1) SELECT 1', []);
        $used = $semantics->analyze('CREATE PROCEDURE p() WITH c (p, q) AS (SELECT 1) SELECT * FROM c', []);
        $once = $semantics->analyze('WITH b AS (SELECT x FROM a) SELECT * FROM b', []);
        $twice = $semantics->analyze('WITH b AS (SELECT x FROM a) SELECT * FROM b, b AS d', []);

        self::assertSame([], $unused->facts->diagnostics);
        self::assertCount(1, $used->facts->diagnostics);
        self::assertCount(count($once->facts->diagnostics), $twice->facts->diagnostics);
    }

    public function testBindDetectsExplicitTableRecursionOnlyWhenUsed(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $used = $semantics->analyze('CREATE PROCEDURE p() WITH RECURSIVE c AS (TABLE c) DELETE FROM c', []);
        $unused = $semantics->analyze('WITH RECURSIVE c AS (TABLE c) SELECT 1', []);
        $unusedSelect = $semantics->analyze('WITH RECURSIVE c AS (SELECT * FROM c) SELECT 1', []);

        self::assertEquals(new Misuse(MisuseRule::RecursiveWithoutUnion, new Name('c')), $used->facts->diagnostics[0]);
        self::assertSame([], $unused->facts->diagnostics);
        self::assertSame([], $unusedSelect->facts->diagnostics);
    }

    public function testBindReportsADuplicateAfterItsQueryEvenInsideAnUnusedDefinition(): void
    {
        $select = static fn (string $value): Select => new Select([], [new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new Name('a')), new NumberLiteral($value)))]);
        $inner = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [
            new CommonTableExpression(new Name('c'), [], $select('1')),
            new CommonTableExpression(new Name('c'), [], $select('2')),
        ]), $select('3'));
        $root = new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression(new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [new CommonTableExpression(new Name('unused'), [], $inner)]), $select('4'));
        $operation = new \SqlSemantics\Statement\Operation((new Semantics(Dialect::MySql, 'mysql-8.4.7'))->context(), new \SqlSemantics\Platform\MySql\Statement\QueryRoot($root));
        $warnings = $operation->facts->warnings;

        self::assertSame([], $operation->sources->notices);
        self::assertCount(5, $warnings);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $warnings[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::class, $warnings[1]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warnings[2]);
        self::assertTrue($warnings[2]->aborts);
        self::assertEquals(new Misuse(MisuseRule::DuplicateCommonTable, new Name('c')), $warnings[2]->problem);
    }

    public function testBindKeepsCaseDistinctCommonTablesSeparate(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('WITH c AS (SELECT 1 AS a), C AS (SELECT 2 AS b) SELECT c.a, C.b FROM c, C', []);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertSame('a', $operation->field(0)->name?->value);
        self::assertSame('b', $operation->field(1)->name?->value);
    }

    public function testExtendedAddsTablesWithoutAQueryLevel(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $outer = $derivation->environment();
        $extended = (new CommonTables())->extended($outer, [new CommonBinding(new Name('c'), new Dual(), new RowShape([]))]);

        self::assertSame($outer->outer, $extended->outer);
        self::assertNotNull($extended->commonTable(new Name('c')));
    }

    public function testPendingFindsTheRecursiveTableARightOperandRefersTo(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $pending = new CommonBinding(new Name('c'), new Dual(), new RowShape([], [new RecursiveReference(new Name('c'))]));
        $scope = (new CommonTables())->extended($derivation->environment(), [$pending]);
        $refers = $semantics->analyze('SELECT 1 FROM c')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $refers);
        $refers = $refers->query;
        $other = $semantics->analyze('SELECT 1 FROM d')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $other);
        $other = $other->query;

        self::assertInstanceOf(Select::class, $refers);
        self::assertInstanceOf(Select::class, $other);
        self::assertSame($pending, (new CommonTables())->pending($scope, $refers, $derivation));
        self::assertNull((new CommonTables())->pending($scope, $other, $derivation));
    }

    public function testAnchoredBindsTheColumnsOfTheNonrecursivePart(): void
    {
        $semantics = new Semantics(Dialect::MySql);
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
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $table = new CommonTableExpression(new Name('c'), [new Name('x')], new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $anchor = new QueryFact([new Field(0, new OutputSlot(new Name('n'), new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull))], $semantics->context()->columnNames);
        $shape = (new CommonTables())->shape(new CommonBinding(new Name('c'), $table, new RowShape([])), $anchor, $derivation);

        self::assertSame('x', $shape->slots[0]->name?->value);
    }

    public function testRecursiveKeepsTheTypeWithoutADerivation(): void
    {
        $type = new NullOnly();

        self::assertSame($type, (new CommonTables())->recursive($type, null));
    }

    public function testRecursiveKeepsATypeWithoutAResolvedDomain(): void
    {
        $type = new Known(new Integral(IntegralKind::BigInt));

        self::assertSame($type, (new CommonTables())->recursive($type, new Derivation((new Semantics(Dialect::MySql))->context())));
    }

    public function testRecursiveSettlesTheTypeAsATemporaryTableColumn(): void
    {
        $settled = (new CommonTables())->recursive(new NullOnly(), new Derivation((new Semantics(Dialect::MySql))->context()));

        self::assertInstanceOf(Known::class, $settled);
        self::assertEquals(new Domain(Kind::String, DomainField::VarString, 0, 0, false, Collation::binary(), [], Coercibility::Ignorable), $settled->descriptor);
    }

    public function testNullableMakesEveryColumnNullable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $anchor = new QueryFact([new Field(0, new OutputSlot(new Name('n'), new Known(new Integral(IntegralKind::BigInt)), Nullability::NotNull))], $semantics->context()->columnNames);

        self::assertSame(Nullability::Nullable, (new CommonTables())->nullable($anchor)->fields()?->at(0)->nullability);
    }

    public function testOperandsAnswersTheChainInWrittenOrder(): void
    {
        $query = (new Semantics(Dialect::MySql))->analyze('(SELECT 1 UNION SELECT 2 UNION SELECT 3)')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        $query = $query->query;

        self::assertInstanceOf(ParenthesizedQuery::class, $query);
        self::assertCount(3, (new CommonTables())->operands($query));
        self::assertInstanceOf(SetOperation::class, $query->query);
        self::assertSame([$query->query->right], array_slice((new CommonTables())->operands($query->query), 2));
        self::assertSame([$query->query->right], (new CommonTables())->operands($query->query->right));
    }

    public function testRefersFindsAnUnqualifiedReference(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $query = $semantics->analyze('SELECT 1 FROM db.c, (SELECT 1 FROM c) AS d')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        $query = $query->query;
        $qualified = $semantics->analyze('SELECT 1 FROM db.c')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $qualified);
        $qualified = $qualified->query;

        self::assertInstanceOf(Select::class, $query);
        self::assertInstanceOf(Select::class, $qualified);
        self::assertTrue((new CommonTables())->refers($query, new Name('c'), $derivation));
        self::assertFalse((new CommonTables())->refers($qualified, new Name('c'), $derivation));
    }

    public function testOperandsWalksALeadingUnion(): void
    {
        $query = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 1 UNION SELECT 2 LIMIT 1 UNION SELECT 3')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        $query = $query->query;

        self::assertInstanceOf(SetOperation::class, $query);
        self::assertCount(3, (new CommonTables())->operands($query));
    }

    public function testShapeKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("WITH RECURSIVE c AS (SELECT 'é' UNION ALL SELECT x FROM c) SELECT 1");

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testNullableKeepsTheInputsAnUnnamedColumnDependsOn(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("WITH RECURSIVE c AS (SELECT 'é' UNION ALL SELECT 1 FROM c) SELECT * FROM c");

        self::assertEquals([new SessionState('character_set_client')], $operation->field(0)->slot->unnamed);
    }

    public function testBindNamesTheProblemsOfTheExpressions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $twice = $semantics->analyze('WITH c AS (SELECT 1), c AS (SELECT 2) SELECT 1')->facts->diagnostics;
        self::assertEquals([new Misuse(MisuseRule::DuplicateCommonTable, new Name('c'))], $twice);
    }

    /**
     * @return iterable<string, array{string, MisuseRule}>
     */
    public static function providerRecursiveRefusals(): iterable
    {
        yield 'missing union' => ['WITH RECURSIVE c AS (SELECT 1 FROM c) SELECT 1 FROM c', MisuseRule::RecursiveWithoutUnion];
        yield 'missing anchor' => ['WITH RECURSIVE c AS (SELECT 1 FROM c UNION SELECT 1) SELECT 1 FROM c', MisuseRule::RecursiveWithoutAnchor];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerRecursiveRefusals')]
    public function testBindRetainsARefusedReadWithoutConstructingAnOrdinaryStatement(string $sql, MisuseRule $rule): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('A contradictory recursive read cannot become an ordinary statement.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedQuery::class, $failure->problem);
            self::assertEquals(new Misuse($rule, new Name('c')), $failure->problem->query->problem());
        }
    }
}
