<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Regexp;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\SoundsLike;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(SoundsLike::class)]
#[Medium]
final class SoundsLikeTest extends TestCase
{
    public function testDeriveScalarIsAnIntegerTruthValue(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new SoundsLike(new StringLiteral(['Smith']), new StringLiteral(['Smyth'])), $derivation->environment());

        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testRenderWritesBothWords(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new SoundsLike(new StringLiteral(['a']), new StringLiteral(['b'])))->render($out);

        self::assertSame("'a' SOUNDS LIKE 'b'", (new Lexical())->join($out->pieces()));
    }

    public function testAPredicateOperandIsRejected(): void
    {
        $this->expectExceptionMessage('The left operand of SOUNDS LIKE needs a grouping to keep its place.');

        new SoundsLike(new Regexp(new StringLiteral(['a']), new StringLiteral(['b'])), new StringLiteral(['c']));
    }
}
