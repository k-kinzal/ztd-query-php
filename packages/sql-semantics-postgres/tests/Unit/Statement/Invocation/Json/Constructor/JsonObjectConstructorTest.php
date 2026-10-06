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
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonItemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\JsonTest;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
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

#[CoversClass(JsonObjectConstructor::class)]
#[Small]
final class JsonObjectConstructorTest extends TestCase
{
    public function testRejectsANullClauseWithoutPairs(): void
    {
        $this->expectExceptionMessage('JSON_OBJECT without pairs takes no NULL or uniqueness clause.');
        new JsonObjectConstructor([], JsonNullHandling::Keep);
    }

    public function testOutputNameIsJsonObject(): void
    {
        self::assertSame('json_object', (new JsonObjectConstructor([]))->outputName()->value);
    }

    public function testDeriveScalarIsJsonOrTheReturningType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $plain = $derivation->scalar(new JsonObjectConstructor([new JsonPair(new Constant(new StringConstant('a')), new JsonValueExpression(new Constant(new IntegerConstant('1'))))]), $derivation->environment());
        $returning = $derivation->scalar(new JsonObjectConstructor([], null, null, new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')]))))), $derivation->environment());
        self::assertEquals([new ScalarFact(new Known(Builtin::Json), Nullability::NotNull), new ScalarFact(new Known(Builtin::Text), Nullability::NotNull)], [$plain, $returning]);
    }

    public function testRenderWritesTheClauses(): void
    {
        $object = new JsonObjectConstructor([new JsonPair(new Constant(new StringConstant('a')), new JsonValueExpression(new Constant(new IntegerConstant('1'))))], JsonNullHandling::Absent, new JsonUniqueKeys(true), new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')])))));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $object->render($out);
        self::assertSame('JSON_OBJECT(\'a\' : 1 ABSENT ON NULL WITH UNIQUE KEYS RETURNING text)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsALastValueThatWouldTakeTheUniqueness(): void
    {
        $this->expectExceptionMessage('A last value ending in IS JSON without a uniqueness clause would take the uniqueness clause; group it.');
        new JsonObjectConstructor([new JsonPair(new NullLiteral(), new JsonValueExpression(new JsonTest(new NullLiteral(), false, JsonItemKind::Json)))], null, new JsonUniqueKeys(false));
    }
}
