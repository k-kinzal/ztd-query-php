<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedTextLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Collation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(CollatedTextLowering::class)]
#[Medium]
final class CollatedTextLoweringTest extends TestCase
{
    public function testLowerResolvesAQualifiedCollationAndDecodedValue(): void
    {
        $query = (new Inspection())->inspect("SELECT 'it''s' COLLATE pg_catalog.\"C\"");
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $item);
        self::assertInstanceOf(Collation::class, $item->expression);

        $value = (new CollatedTextLowering())->lower($item->expression, new SourceBinding($query->binding(), null), true);
        self::assertInstanceOf(TextLiteral::class, $value->operand);
        self::assertSame("it's", $value->operand->value);
        self::assertSame(TextCollation::C, $value->collation);
    }

    public function testOperandResolvesTypedNull(): void
    {
        $query = (new Inspection())->inspect('SELECT CAST(NULL AS pg_catalog.text)');
        self::assertInstanceOf(Select::class, $query->statement);
        $item = $query->statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $item);

        self::assertInstanceOf(NullLiteral::class, (new CollatedTextLowering())->operand(
            $item->expression,
            new SourceBinding($query->binding(), null),
            true
        ));
    }
}
