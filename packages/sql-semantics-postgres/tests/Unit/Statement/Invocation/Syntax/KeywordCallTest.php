<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\KeywordFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

#[CoversClass(KeywordCall::class)]
#[Small]
final class KeywordCallTest extends TestCase
{
    public function testRejectsJsonObjectWithoutArguments(): void
    {
        $this->expectExceptionMessage('Call arguments are arguments.');
        new KeywordCall(KeywordFunction::JsonObject, []);
    }

    public function testOutputNameIsTheFunctionName(): void
    {
        self::assertSame('overlay', (new KeywordCall(KeywordFunction::Overlay, []))->outputName()->value);
    }

    public function testDeriveScalarSearchesThePathForSubstring(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new KeywordCall(KeywordFunction::Substring, [new PositionalArgument(new Constant(new StringConstant('abc'))), new PositionalArgument(new Constant(new IntegerConstant('1')))]), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testDeriveScalarLeavesNamedArgumentsToTheRoutine(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new KeywordCall(KeywordFunction::JsonObject, [new NamedArgument(new Name('a'), new Constant(new StringConstant('x')))]), $derivation->environment());
        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('json_object'), new Name('pg_catalog')))]), $fact->type);
    }

    public function testRenderWritesTheKeywordAndTheArguments(): void
    {
        $call = new KeywordCall(KeywordFunction::JsonObject, [new PositionalArgument(new Constant(new StringConstant('{a,b}')))]);
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $call->render($out);
        self::assertSame('JSON_OBJECT(\'{a,b}\')', (new Lexical())->join($out->pieces()));
    }
}
