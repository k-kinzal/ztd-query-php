<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CharacterStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\LiteralDefaultLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(LiteralDefaultLowering::class)]
#[Medium]
final class LiteralDefaultLoweringTest extends TestCase
{
    public function testLowerResolvesTheTextDefaultInItsColumnContext(): void
    {
        $query = (new Inspection())->inspect("CREATE TABLE app.a (label pg_catalog.text DEFAULT 'a''b')");
        self::assertInstanceOf(CreateTable::class, $query->statement);
        self::assertInstanceOf(ListedColumns::class, $query->statement->definition);
        $column = $query->statement->definition->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $clause = $column->qualifiers[0];
        self::assertInstanceOf(DefaultExpression::class, $clause);

        $value = (new LiteralDefaultLowering())->lower($query->binding(), $clause, CharacterStorage::Text);
        self::assertInstanceOf(TextLiteral::class, $value);
        self::assertSame("a'b", $value->value);
    }

    public function testLowerRetainsTheResolvedSourceOfANarrowingAssignment(): void
    {
        $query = (new Inspection())->inspect('CREATE TABLE app.a (v SMALLINT DEFAULT CAST(7 AS pg_catalog.int8))');
        self::assertInstanceOf(CreateTable::class, $query->statement);
        self::assertInstanceOf(ListedColumns::class, $query->statement->definition);
        $column = $query->statement->definition->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $clause = $column->qualifiers[0];
        self::assertInstanceOf(DefaultExpression::class, $clause);

        $value = (new LiteralDefaultLowering())->lower($query->binding(), $clause, IntegerStorage::Small);

        self::assertInstanceOf(WideIntegerLiteral::class, $value);
        self::assertSame('7', $value->decimal);
    }
}
