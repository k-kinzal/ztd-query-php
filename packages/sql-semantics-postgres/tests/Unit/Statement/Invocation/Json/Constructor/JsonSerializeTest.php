<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonSerialize;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonSerialize::class)]
#[Small]
final class JsonSerializeTest extends TestCase
{
    public function testOutputNameIsJsonSerialize(): void
    {
        self::assertSame('json_serialize', (new JsonSerialize(new JsonValueExpression(new NullLiteral())))->outputName()->value);
    }

    public function testDeriveScalarIsTextWithoutReturning(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonSerialize(new JsonValueExpression(new Constant(new StringConstant('{}')))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::NotNull), $fact);
    }

    public function testRenderWritesTheReturningClause(): void
    {
        $serialize = new JsonSerialize(new JsonValueExpression(new Constant(new StringConstant('{}'))), new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')])))));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $serialize->render($out);
        self::assertSame('JSON_SERIALIZE(\'{}\' RETURNING text)', (new Lexical())->join($out->pieces()));
    }
}
