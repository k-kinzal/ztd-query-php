<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Call\TypeClass;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonValueCall::class)]
#[Small]
final class JsonValueCallTest extends TestCase
{
    public function testDeriveScalarAnswersTheReturningType(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new JsonValueCall(new StringLiteral(['x']), new StringLiteral(['$.a']), new CastTarget(CastKind::Unsigned), new JsonResponse(JsonResponseKind::Default, new NumberLiteral('1'))), $derivation->environment());

        self::assertEquals(new Known(new CastTarget(CastKind::Unsigned)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarAnswersACharacterStringWithoutReturning(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new JsonValueCall(new StringLiteral(['x']), new StringLiteral(['$.a'])), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
    }

    public function testRenderWritesTheResponses(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new JsonValueCall(new ColumnUse(new Name('a')), new StringLiteral(['$.a']), null, new JsonResponse(JsonResponseKind::Null), new JsonResponse(JsonResponseKind::Error)))->render($out);

        self::assertSame("JSON_VALUE(a, '$.a' NULL ON EMPTY ERROR ON ERROR)", (new Lexical())->join($out->pieces()));
    }
}
