<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Operation;

#[CoversClass(ValueRow::class)]
#[Medium]
final class ValueRowTest extends TestCase
{
    public function testRenderWritesTheValuesInParentheses(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("values (1, 'a', null), (2, 'b', 3.5)");

        self::assertInstanceOf(ValuesClause::class, $query->statement);
        self::assertCount(3, $query->statement->rows[1]->values);
        self::assertInstanceOf(TextLiteral::class, $query->statement->rows[0]->values[1]);
        self::assertSame('a', $query->statement->rows[0]->values[1]->value);
        self::assertSame("VALUES (1, 'a', NULL), (2, 'b', 3.5)", $query->toString());
    }

    public function testRenderWritesANewlyBuiltRow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $operation = new Operation($semantics->context(), new ValuesClause([new ValueRow([new IntegerLiteral('1'), new TextLiteral('x')])]));

        self::assertSame("VALUES (1, 'x')", $operation->toString());
    }

    public function testRenderRefusesAnEmptyRow(): void
    {
        $this->expectExceptionMessage('A row of values has at least one expression.');

        new ValueRow([]);
    }
}
