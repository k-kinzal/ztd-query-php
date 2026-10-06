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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonObjectAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonPair;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonUniqueKeys;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonObjectAggregate::class)]
#[Small]
final class JsonObjectAggregateTest extends TestCase
{
    public function testOutputNameIsJsonObjectagg(): void
    {
        self::assertSame('json_objectagg', (new JsonObjectAggregate(new JsonPair(new Constant(new StringConstant('a')), new JsonValueExpression(new Constant(new IntegerConstant('1'))))))->outputName()->value);
    }

    public function testDeriveScalarDerivesTheFilterAndTheWindow(): void
    {
        $filter = new BooleanLiteral(true);
        $partition = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonObjectAggregate(new JsonPair(new Constant(new StringConstant('a')), new JsonValueExpression(new Constant(new IntegerConstant('1')))), null, null, null, $filter, new WindowSpecification(null, [$partition])), $derivation->environment());
        self::assertTrue($derivation->facts()->covers($filter));
        self::assertTrue($derivation->facts()->covers($partition));
        self::assertEquals(new ScalarFact(new Known(Builtin::Json), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheClausesFilterAndWindow(): void
    {
        $aggregate = new JsonObjectAggregate(new JsonPair(new Constant(new StringConstant('a')), new JsonValueExpression(new Constant(new IntegerConstant('1')))), JsonNullHandling::Keep, new JsonUniqueKeys(false), null, new BooleanLiteral(true), new Name('w'));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $aggregate->render($out);
        self::assertSame('JSON_OBJECTAGG(\'a\' : 1 NULL ON NULL WITHOUT UNIQUE KEYS) FILTER (WHERE TRUE) OVER w', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAValueThatWouldTakeTheUniqueness(): void
    {
        $this->expectExceptionMessage('A value ending in IS JSON without a uniqueness clause would take the uniqueness clause; group it.');
        new JsonObjectAggregate(new JsonPair(new NullLiteral(), new JsonValueExpression(new JsonTest(new NullLiteral(), false, JsonItemKind::Json))), null, new JsonUniqueKeys(true));
    }
}
