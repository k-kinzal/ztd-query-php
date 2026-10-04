<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(BooleanLiteral::class)]
#[Medium]
final class BooleanLiteralTest extends TestCase
{
    public function testDeriveScalarAnswersBigIntThatIsNeverNull(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a FROM t WHERE a = TRUE');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(Comparison::class, $select->where);
        $literal = $select->where->right;
        self::assertInstanceOf(BooleanLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertTrue($literal->value);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Integral::class, $fact->type->descriptor);
        self::assertSame(IntegralKind::BigInt, $fact->type->descriptor->kind);
        self::assertFalse($fact->type->descriptor->unsigned());
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarTypesFalseLikeTrue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT FALSE');
        $select = $operation->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $literal = $item->expression;
        self::assertInstanceOf(BooleanLiteral::class, $literal);
        $fact = $operation->facts->scalar($literal);

        self::assertFalse($literal->value);
        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Integral::class, $fact->type->descriptor);
        self::assertSame(IntegralKind::BigInt, $fact->type->descriptor->kind);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
    }

    public function testRenderWritesTheKeywordInUpperCase(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('select a from t where a = false');

        self::assertSame('SELECT a FROM t WHERE a = FALSE', $operation->toString());
    }

    public function testRenderWritesTrue(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));
        (new BooleanLiteral(true))->render($out);

        self::assertSame('TRUE', (new Lexical())->join($out->pieces()));
    }
}
