<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(ReadColumns::class)]
#[Small]
final class ReadColumnsTest extends TestCase
{
    public function testInputPreservesAReadConditionBeforeItsRenameList(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()]);
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->input($query, [new \SqlSemantics\Statement\Identifier\Name('a')]));
    }

    public function testNamedRejectsAContradictoryFixedRenameList(): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]);
        $names = [new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\Name('b')];
        self::assertSame(1, (new ReadColumns())->named($query, $names)?->actual);
        self::assertNull((new ReadColumns())->named($query, []));
    }

    public function testTemplatesChecksTheReadRoleOfNestedValues(): void
    {
        $row = new M\Query\ValueRow([]);
        $table = new M\Relation\DerivedTable(new M\Query\ValuesQuery([$row]), new \SqlSemantics\Statement\Identifier\Name('q'));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], $table);
        $problem = (new ReadColumns())->templates($query);
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $problem);
        self::assertSame($row, $problem->valueRow);
    }

    public function testTemplatesExcludesAnUnusedDefinitionFromReadConstraints(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('WITH c AS (SELECT ROW(1,2)), d AS (SELECT * FROM c) SELECT 1');
        self::assertInstanceOf(M\QueryRoot::class, $operation->statement);
        self::assertNull((new ReadColumns())->templates($operation->statement->query));
    }

    public function testProjectionChecksStarsBeforeCatalogResolution(): void
    {
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->projection(new M\Query\Select([], [new M\Query\Star()])));
        self::assertNull((new ReadColumns())->projection(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))])));
    }

    public function testProblemDistinguishesExistenceFromReadingColumns(): void
    {
        $query = new M\Query\Select([], [new M\Query\Star()]);
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->problem($query));
        self::assertNull((new ReadColumns())->problem($query, true));
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->problem(new M\Query\ParenthesizedQuery($query)));
    }

    public function testProblemRejectsEmptyQualifiersIndependentlyOfTheInputRelation(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name(''));
        $query = new M\Query\Select([], [new M\Name\TableWildcard($name)], new M\Relation\Dual());
        self::assertInstanceOf(M\Query\Problem\Misuse::class, (new ReadColumns())->problem($query));
        self::assertNull((new ReadColumns())->problem($query, true));
    }

    public function testProblemRequiresAFromRelationForAQualifiedProjection(): void
    {
        $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'));
        $query = new M\Query\Select([], [new M\Name\TableWildcard($name)]);
        self::assertEquals(new M\Query\Problem\UnknownQualifier($name), (new ReadColumns())->problem($query));
        self::assertNull((new ReadColumns())->problem($query, true));
        self::assertEquals(new M\Query\Problem\UnknownQualifier($name), (new ReadColumns())->problem(new M\Query\Select([], [new M\Name\TableWildcard($name)], new M\Relation\Dual())));
        self::assertNull((new ReadColumns())->problem(new M\Query\Select([], [new M\Name\TableWildcard($name)], new M\Relation\TableReference($name))));
    }

    public function testRowsDistinguishesDefaultsEmptyAndInconsistentWidths(): void
    {
        $rules = new ReadColumns();
        self::assertInstanceOf(M\Query\Problem\Misuse::class, $rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([])])));
        self::assertInstanceOf(M\Dml\Problem\WriteMisuse::class, $rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Dml\DefaultRequest()])])));
        self::assertInstanceOf(M\Query\Problem\CountMismatch::class, $rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')]), new M\Query\ValueRow([])])));
        self::assertNull($rules->rows(new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])])));
    }

    public function testCombinedLeavesUnknownCatalogWidthsForResolution(): void
    {
        $one = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1')])]);
        $two = new M\Query\ValuesQuery([new M\Query\ValueRow([new M\Literal\NumberLiteral('1'), new M\Literal\NumberLiteral('2')])]);
        self::assertInstanceOf(M\Query\Problem\CountMismatch::class, (new ReadColumns())->combined($one, $two));
        self::assertNull((new ReadColumns())->combined($one, $one));
        self::assertNull((new ReadColumns())->combined($one, new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))));
    }
    public function testWindowsValidateOffsetsOnlyWhenTheSameBlockNamesThem(): void
    {
        $window = new M\Query\Clause\WindowDefinition(new \SqlSemantics\Statement\Identifier\Name('w'), new M\Call\Window\WindowSpec(frame: new M\Call\Window\Frame(M\Call\Window\FrameUnit::Rows, new M\Call\Window\FrameBound(M\Call\Window\FrameBoundKind::Preceding, new M\Literal\NumberLiteral('1.5')))));
        $read = new M\Query\Select([], [new M\Query\SelectExpression(new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new \SqlSemantics\Statement\Identifier\Name('W')))], windows: [$window]);
        self::assertInstanceOf(M\Call\Problem\FrameConstraint::class, (new ReadColumns())->windows($read));
        self::assertNull((new ReadColumns())->windows(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], windows: [$window])));
    }

}
