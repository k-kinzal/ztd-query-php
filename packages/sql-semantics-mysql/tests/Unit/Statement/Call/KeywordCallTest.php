<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
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

        self::assertEquals(new Known(Domain::string(2, Collation::known('utf8mb4_0900_ai_ci'), Field::VarString, Coercibility::Coercible)), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarTypesAddDateAsDateArithmetic(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new KeywordCall(KeywordFunction::AddDate, [new TemporalLiteral(TemporalForm::Date, '2024-01-01'), new NumberLiteral('1')]), $derivation->environment());

        self::assertEquals(new Known(new Domain(Kind::Date, Field::Date, 10)), $fact->type);
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

    public function testDeriveScalarWarnsThatOldPasswordIsDeprecatedIn56(): void
    {
        $platform = new Platform();
        $derivation = new Derivation($platform->context($platform->profile('mysql-5.6.51', null, ParameterStyle::Native), null, [], false));
        $derivation->scalar(new KeywordCall(KeywordFunction::OldPassword, [new StringLiteral(['x'])]), $derivation->environment());

        self::assertEquals([new Deprecation(Deprecated::OldPassword)], $derivation->facts()->warnings);
    }
}
