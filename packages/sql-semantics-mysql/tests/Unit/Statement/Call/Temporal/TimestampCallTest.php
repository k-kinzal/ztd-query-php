<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Temporal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(TimestampCall::class)]
#[Small]
final class TimestampCallTest extends TestCase
{
    public function testDeriveScalarTypesAnAdditionAsDateArithmetic(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new TimestampCall(TimestampOperation::Add, IntervalUnit::Day, new NumberLiteral('1'), new TemporalLiteral(TemporalForm::Date, '2024-01-01')), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Date->descriptor()), $fact->type);
    }

    public function testDeriveScalarTypesADifferenceAsAnInteger(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new TimestampCall(TimestampOperation::Difference, IntervalUnit::Day, new TemporalLiteral(TemporalForm::Date, '2024-01-01'), new TemporalLiteral(TemporalForm::Date, '2024-01-01')), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Integer->descriptor()), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderWritesTheUnitFirst(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new TimestampCall(TimestampOperation::Difference, IntervalUnit::Week, new ColumnUse(new Name('a')), new ColumnUse(new Name('a'))))->render($out);

        self::assertSame('TIMESTAMPDIFF(WEEK, a, a)', (new Lexical())->join($out->pieces()));
    }
}
