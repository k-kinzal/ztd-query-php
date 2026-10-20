<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Update;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\Update\Structure;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Structure::class)]
#[Small]
final class StructureTest extends TestCase
{
    public function testTargetLeavesJoinedReadSourcesAvailableForPhysicalWrites(): void
    {
        $source = new M\Relation\DerivedTable(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]), new Name('q'));
        $physical = new M\Relation\TableReference(new QualifiedName(new Name('t')));
        $assignment = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a'), new QualifiedName(new Name('t'))), new M\Literal\NumberLiteral('2'));
        $rules = new Structure();
        self::assertNull($rules->target([$source, $physical], [$assignment]));
        self::assertSame('q', $rules->target([$source], [$assignment])?->name->value);
        $readOnly = new M\Dml\Assignment(new M\Name\ColumnUse(new Name('a'), new QualifiedName(new Name('q'))), new M\Literal\NumberLiteral('2'));
        self::assertSame($readOnly->column->qualifier, $rules->target([$source, $physical], [$readOnly]));
        self::assertNull($rules->target([$source, new M\Relation\TableReference(new QualifiedName(new Name('Q')))], [$readOnly]));
    }

    public function testTargetDistinguishesExactCommonNamesFromQualifiedPhysicalNames(): void
    {
        $name = new Name('q');
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'), new Name('a'))]);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression($name, [], $query)]);
        $rules = new Structure();
        self::assertSame($name, $rules->target([new M\Relation\TableReference(new QualifiedName($name))], [], $with)?->name);
        self::assertNull($rules->target([new M\Relation\TableReference(new QualifiedName(new Name('Q')))], [], $with));
        self::assertNull($rules->target([new M\Relation\TableReference(new QualifiedName($name, new Name('d')))], [], $with));
    }

    public function testProblemRejectsOrderAndLimitForMultipleOccurrences(): void
    {
        $tables = [new M\Relation\TableReference(new QualifiedName(new Name('t'))), new M\Relation\TableReference(new QualifiedName(new Name('u')))];
        $order = [new M\Query\OrderItem(new M\Name\ColumnUse(new Name('a')))];
        $limit = new M\Query\Clause\RowLimit(new M\Literal\NumberLiteral('1'));
        $rules = new Structure();
        $ordered = $rules->problem(null, $tables, [], $order);
        $limited = $rules->problem(null, $tables, [], limit: $limit);
        self::assertInstanceOf(M\Dml\Problem\WriteMisuse::class, $ordered);
        self::assertInstanceOf(M\Dml\Problem\WriteMisuse::class, $limited);
        self::assertSame(M\Dml\Problem\WriteRule::OrderedMultipleUpdate, $ordered->rule);
        self::assertSame(M\Dml\Problem\WriteRule::LimitedMultipleUpdate, $limited->rule);
        self::assertNull($rules->problem(null, [$tables[0]], [], $order, $limit));
    }
}
