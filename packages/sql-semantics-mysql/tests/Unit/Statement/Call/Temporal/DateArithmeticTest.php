<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Rendering\Lexical;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;

#[CoversClass(DateArithmetic::class)]
#[Small]
final class DateArithmeticTest extends TestCase
{
    public function testDeriveScalarFollowsTheDateAndTheUnit(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new DateArithmetic(true, new TemporalLiteral(TemporalForm::Date, '2024-01-01'), new NumberLiteral('1'), IntervalUnit::Second), $derivation->environment());

        self::assertEquals(new Known(TypeClass::DateTime->descriptor()), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesDateSub(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new DateArithmetic(true, new ColumnUse(new Name('a')), new NumberLiteral('1'), IntervalUnit::Second))->render($out);

        self::assertSame('DATE_SUB(a, INTERVAL 1 SECOND)', (new Lexical())->join($out->pieces()));
    }
}
