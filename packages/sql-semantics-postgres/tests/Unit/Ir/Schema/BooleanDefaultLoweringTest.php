<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\BooleanDefaultLowering;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(BooleanDefaultLowering::class)]
#[Medium]
final class BooleanDefaultLoweringTest extends TestCase
{
    public function testLowerResolvesTheBuiltinBooleanInputCast(): void
    {
        $query = (new Inspection())->inspect("CREATE TABLE app.a (value BOOLEAN DEFAULT CAST(' YE ' AS pg_catalog.bool))");
        self::assertInstanceOf(CreateTable::class, $query->statement);
        self::assertInstanceOf(ListedColumns::class, $query->statement->definition);
        $column = $query->statement->definition->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $clause = $column->qualifiers[0];
        self::assertInstanceOf(DefaultExpression::class, $clause);

        $value = (new BooleanDefaultLowering())->lower($query->binding(), $clause->value);

        self::assertInstanceOf(BooleanLiteral::class, $value);
        self::assertTrue($value->value);
    }

    public function testFromTextFoldsAsciiAndResolvesAnUnambiguousPrefix(): void
    {
        $lowering = new BooleanDefaultLowering();

        self::assertTrue($lowering->fromText("\n \t tR \r")->value);
        self::assertFalse($lowering->fromText(" \t OF\013 ")->value);
    }
}
