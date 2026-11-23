<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Schema\LiteralDefaultLowering;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LiteralDefaultLowering::class)]
#[Medium]
final class LiteralDefaultLoweringTest extends TestCase
{
    public function testLowerKeepsTheExactBigintLimit(): void
    {
        $query = (new Inspection())->inspect('CREATE TABLE app.a (amount BIGINT DEFAULT -9223372036854775808)');
        self::assertInstanceOf(CreateTable::class, $query->statement);
        $column = $query->statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $clause = $column->specification->columnAttributes()[0];
        self::assertInstanceOf(DefaultLiteral::class, $clause);

        $literal = (new LiteralDefaultLowering())->lower($clause->value);

        self::assertInstanceOf(RangeIntegerLiteral::class, $literal);
        self::assertSame('-9223372036854775808', $literal->decimal);
    }
}
