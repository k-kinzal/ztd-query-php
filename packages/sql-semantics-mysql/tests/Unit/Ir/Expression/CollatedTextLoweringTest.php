<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Collated;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CollatedTextLowering::class)]
#[Medium]
final class CollatedTextLoweringTest extends TestCase
{
    public function testLowerResolvesTheBuiltinOverADecodedLiteral(): void
    {
        $query = (new Inspection())->inspect("SELECT 'a''b ' COLLATE utf8mb4_bin AS n");
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        self::assertInstanceOf(Collated::class, $item->expression);
        $value = (new CollatedTextLowering())->lower($item->expression, new SourceBinding($query->binding(), null));

        self::assertSame(TextCollation::Utf8mb4Bin, $value->collation);
        self::assertInstanceOf(TextLiteral::class, $value->operand);
        self::assertSame("a'b ", $value->operand->value);
    }
}
