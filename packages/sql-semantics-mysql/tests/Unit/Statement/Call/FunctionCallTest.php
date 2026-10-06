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
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(FunctionCall::class)]
#[Small]
final class FunctionCallTest extends TestCase
{
    public function testNamedTellsWhetherAnArgumentHasAnAlias(): void
    {
        self::assertTrue((new FunctionCall(new Name('f'), [new CallArgument(new NumberLiteral('1'), new Name('x'))]))->named());
        self::assertFalse((new FunctionCall(new Name('f'), [new CallArgument(new NumberLiteral('1'))]))->named());
    }

    public function testDeriveScalarTypesANativeFunction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new FunctionCall(new Name('concat'), [new CallArgument(new StringLiteral(['x'])), new CallArgument(new NumberLiteral('1'))]), $derivation->environment());

        self::assertEquals(new Known(TypeClass::Character->descriptor()), $fact->type);
        self::assertSame(Nullability::NotNull, $fact->nullability);
    }

    public function testDeriveScalarDependsOnAStoredFunction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $derivation = new Derivation($platform->context($profile, null, [], false));
        $fact = $derivation->scalar(new FunctionCall(new Name('score'), [new CallArgument(new NumberLiteral('1'))], new Name('db')), $derivation->environment());

        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('score'), new Name('db')))]), $fact->type);
        self::assertSame(Nullability::Dependent, $fact->nullability);
    }

    public function testRenderGluesTheNameToItsParenthesis(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new FunctionCall(new Name('score'), [new CallArgument(new NumberLiteral('1'))], new Name('db')))->render($out);

        self::assertSame('db.score(1)', (new Lexical())->join($out->pieces()));
    }

    public function testRenderQuotesAKeywordName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $out = new Output($platform->codec($profile));
        (new FunctionCall(new Name('count'), []))->render($out);

        self::assertSame('`count`()', (new Lexical())->join($out->pieces()));
    }
}
