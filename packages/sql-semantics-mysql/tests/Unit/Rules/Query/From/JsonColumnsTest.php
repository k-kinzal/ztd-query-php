<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\From\JsonColumns;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(JsonColumns::class)]
#[Small]
final class JsonColumnsTest extends TestCase
{
    public function testProblemRetainsTheEarlierCaseOfTheFirstRepeatedOutput(): void
    {
        $columns = array_map(static fn (string $name): M\Call\Json\OrdinalityColumn => new M\Call\Json\OrdinalityColumn(new Name($name)), ['a', 'é', 'É', 'a']);
        self::assertSame($columns[1]->name, (new JsonColumns())->problem($columns)?->column);
        self::assertNull((new JsonColumns())->problem(array_slice($columns, 0, 2)));
    }

    public function testNamesFlattensNestedColumnsWithoutCreatingAnotherNamespace(): void
    {
        $column = new M\Call\Json\OrdinalityColumn(new Name('b'));
        $nested = new M\Call\Json\NestedColumns(new M\Literal\StringLiteral(['$']), [$column]);
        self::assertSame([$column->name], (new JsonColumns())->names([$nested]));
    }

    public function testOfFindsTheContradictionWithoutConstructingAnOperation(): void
    {
        $columns = [new M\Call\Json\OrdinalityColumn(new Name('b')), new M\Call\Json\OrdinalityColumn(new Name('B'))];
        $table = new M\Relation\JsonTable(new M\Literal\NullLiteral(), new M\Literal\StringLiteral(['$']), $columns, new Name('j'));
        self::assertSame('b', (new JsonColumns())->of($table)?->column->value);
    }

    public function testInputsExcludesUnusedCommonTablesButFollowsReachedDefinitions(): void
    {
        $columns = [new M\Call\Json\OrdinalityColumn(new Name('b')), new M\Call\Json\OrdinalityColumn(new Name('B'))];
        $table = new M\Relation\JsonTable(new M\Literal\NullLiteral(), new M\Literal\StringLiteral(['$']), $columns, new Name('j'));
        $query = new M\Query\Select([], [new M\Query\Star()], $table);
        $with = new M\Query\With\With(false, [new M\Query\With\CommonTableExpression(new Name('c'), [], $query)]);
        self::assertNull((new JsonColumns())->inputs([], $with));
        $read = new M\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('c')));
        self::assertSame('b', (new JsonColumns())->inputs([$read], $with)?->column->value);
    }
}
