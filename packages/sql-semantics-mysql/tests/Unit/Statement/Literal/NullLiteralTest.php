<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NullLiteral::class)]
#[Medium]
final class NullLiteralTest extends TestCase
{
    public function testDeriveScalarAnswersTheNullOnlyTypeThatIsNullable(): void
    {
        $operation = (new Inspection())->inspect('SELECT a FROM t WHERE a = NULL');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(NullLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertInstanceOf(NullOnly::class, $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheKeywordInUpperCase(): void
    {
        $operation = (new Inspection())->inspect('select null');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);

        self::assertInstanceOf(NullLiteral::class, $item->expression);
        self::assertSame('SELECT NULL', $operation->toString());
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
    }
}
