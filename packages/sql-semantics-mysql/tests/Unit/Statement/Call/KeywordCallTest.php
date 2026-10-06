<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(KeywordCall::class)]
#[Small]
final class KeywordCallTest extends TestCase
{
    public function testDeriveScalarTypesTheFunction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new KeywordCall(KeywordFunction::If, [new NumberLiteral('1'), new StringLiteral(['x']), new NumberLiteral('1')]), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarTypesAddDateAsDateArithmetic(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new KeywordCall(KeywordFunction::AddDate, [new TemporalLiteral(TemporalForm::Date, '2024-01-01'), new NumberLiteral('1')]), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Date->descriptor()), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testRenderGluesTheKeywordToItsArguments(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new KeywordCall(KeywordFunction::CurrentUser, []))->render($out);

        self::assertSame('CURRENT_USER()', (new Lexical())->join($out->pieces()));
    }
}
