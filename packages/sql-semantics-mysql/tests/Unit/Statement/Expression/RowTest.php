<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Row::class)]
#[Medium]
final class RowTest extends TestCase
{
    public function testDeriveScalarIsARowOfTheWidth(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new Row([new NumberLiteral('1'), new NullLiteral(), new NumberLiteral('3')]), $derivation->environment());

        self::assertInstanceOf(Known::class, $fact->type);
        self::assertInstanceOf(Tuple::class, $fact->type->descriptor);
        self::assertSame([3, Nullability::Nullable], [$fact->type->descriptor->width, $fact->nullability]);
    }

    public function testRenderWritesTheElementsInParentheses(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Row([new NumberLiteral('1'), new NumberLiteral('2')]))->render($out);

        self::assertSame('(1, 2)', (new Lexical())->join($out->pieces()));
    }

    public function testASingleElementIsRejected(): void
    {
        $this->expectExceptionMessage('A row constructor has at least two elements.');

        new Row([new NumberLiteral('1')]);
    }
}
