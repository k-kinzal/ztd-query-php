<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Like::class)]
#[Medium]
final class LikeTest extends TestCase
{
    public function testDeriveScalarIsNullWhenTheEscapeCanBe(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.6.51', null, ParameterStyle::Native), null, [], true));

        self::assertSame(Nullability::Nullable, $derivation->scalar(new Like(new StringLiteral(['a']), new StringLiteral(['b']), new NullLiteral()), $derivation->environment())->nullability);
        self::assertSame(Nullability::NotNull, $derivation->scalar(new Like(new StringLiteral(['a']), new StringLiteral(['b'])), $derivation->environment())->nullability);
    }

    public function testRenderWritesTheEscapeClause(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Like(new StringLiteral(['a']), new StringLiteral(['b!%']), new StringLiteral(['!']), true))->render($out);

        self::assertSame("'a' NOT LIKE 'b!%' ESCAPE '!'", (new Lexical())->join($out->pieces()));
    }

    public function testAnArithmeticPatternIsRejected(): void
    {
        $this->expectExceptionMessage('The pattern of LIKE needs a grouping to keep its place.');

        new Like(new StringLiteral(['a']), new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')));
    }
}
