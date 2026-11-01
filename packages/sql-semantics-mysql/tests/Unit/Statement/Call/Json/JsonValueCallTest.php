<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponse;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonValueCall::class)]
#[Small]
final class JsonValueCallTest extends TestCase
{
    public function testPathStepsAreAvailableBeforeOperationDerivation(): void
    {
        $call = new JsonValueCall(new StringLiteral(['{}']), new StringLiteral(['$[last-1].a']));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path::class, $call->pathValue);
        self::assertSame('a', $call->pathValue->legs[1]->name);
        self::assertNotNull($call->pathValue->legs[0]->from);
        self::assertSame(1, $call->pathValue->legs[0]->from->offset);
    }

    public function testPathValueRetainsExplicitSelectionSteps(): void
    {
        $path = new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path([
            new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg(\SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind::Cell, from: new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index(false, 1)),
        ]);
        $call = new JsonValueCall(new StringLiteral(['[10,20]']), new StringLiteral(['$[1]']), pathValue: $path);
        self::assertSame($path, $call->pathValue);
        self::assertSame($path, \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($call)->pathValue);
    }

    public function testPathValueRejectsStepsThatDisagreeWithTheLiteral(): void
    {
        $this->expectExceptionMessage('JSON path steps must agree with the decoded path literal.');
        new JsonValueCall(new StringLiteral(['[10,20]']), new StringLiteral(['$[1]']), pathValue: new \SqlSemantics\Platform\MySql\Statement\Call\Json\Path([]));
    }

    public function testInvalidDocumentTypeIsOwnedByItsCall(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $call = new JsonValueCall(new NumberLiteral('1'), new StringLiteral(['']));
        $derivation->scalar($call, $derivation->environment());
        $problem = $derivation->facts()->diagnostics[0];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\Problem\InvalidJsonDocument::class, $problem);
        self::assertSame($call, $problem->expression);
    }

    public function testDeriveScalarAnswersTheReturningType(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new JsonValueCall(new StringLiteral(['x']), new StringLiteral(['$.a']), new CastTarget(CastKind::Unsigned), new JsonResponse(JsonResponseKind::Default, new NumberLiteral('1'))), $derivation->environment());

        self::assertEquals(new Known(Domain::integer(Field::LongLong, 21, true)), $fact->type);
        self::assertSame(Nullability::Nullable, $fact->nullability);
    }

    public function testDeriveScalarAnswersACharacterStringWithoutReturning(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new JsonValueCall(new StringLiteral(['x']), new StringLiteral(['$.a'])), $derivation->environment());

        self::assertEquals(new Known(Domain::string(512, Collation::known('utf8mb4_0900_bin'), Field::VarString, Coercibility::Coercible)), $fact->type);
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
