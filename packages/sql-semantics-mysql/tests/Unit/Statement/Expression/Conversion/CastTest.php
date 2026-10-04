<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Conversion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Cast::class)]
#[Medium]
final class CastTest extends TestCase
{
    public function testDeriveScalarHasTheTargetTypeAndADateCanBeNull(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.4.7', null, ParameterStyle::Native), null, [], true));
        $fact = $derivation->scalar(new Cast(new StringLiteral(['x']), new CastTarget(CastKind::DateTime, '3')), $derivation->environment());

        self::assertEquals(new Known(new Temporal(TemporalKind::DateTime, '3')), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarIsJsonForAnArray(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-8.0.44', null, ParameterStyle::Native), null, [], true));

        self::assertEquals(new Known(new Elementary(ElementaryKind::Json)), $derivation->scalar(new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Unsigned), true), $derivation->environment())->type);
    }

    public function testDeriveScalarRejectsAnArrayBeforeMySql80(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.7.44', null, ParameterStyle::Native), null, [], true));

        $this->expectExceptionMessage('A cast to an array needs MySQL 8.0 or later.');

        $derivation->scalar(new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Unsigned), true), $derivation->environment());
    }

    public function testRenderGluesTheParenthesisToCast(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new Cast(new NumberLiteral('1'), new CastTarget(CastKind::Char, '2'), true))->render($out);

        self::assertSame('CAST(1 AS CHAR(2) ARRAY)', (new Lexical())->join($out->pieces()));
    }
}
