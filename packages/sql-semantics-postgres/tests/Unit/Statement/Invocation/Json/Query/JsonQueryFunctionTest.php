<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json\Query;

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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQueryFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQuoting;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapping;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
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

#[CoversClass(JsonQueryFunction::class)]
#[Small]
final class JsonQueryFunctionTest extends TestCase
{
    public function testRejectsAWrapperOutsideJsonQuery(): void
    {
        $this->expectExceptionMessage('Only JSON_QUERY takes a wrapper or quotes clause.');
        new JsonQueryFunction(JsonFunctionKind::Value, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$.a')), [], null, new JsonWrapping(JsonWrapperKind::Without));
    }

    public function testRejectsReturningOnJsonExists(): void
    {
        $this->expectExceptionMessage('JSON_EXISTS takes no RETURNING and no ON EMPTY.');
        new JsonQueryFunction(JsonFunctionKind::Exists, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$.a')), [], new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')])))));
    }

    public function testOutputNameIsTheFunction(): void
    {
        self::assertSame('json_value', (new JsonQueryFunction(JsonFunctionKind::Value, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$.a'))))->outputName()->value);
    }

    public function testDeriveScalarIsTheReturningType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonQueryFunction(JsonFunctionKind::Value, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$.a')), [], new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')]))))), $derivation->environment());
        self::assertEquals(new ScalarFact(new Known(Builtin::Text), Nullability::Nullable), $fact);
    }

    public function testDeriveScalarReportsAnInvalidBehavior(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonQueryFunction(JsonFunctionKind::Exists, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$.a')), [], null, null, null, new JsonBehaviorClause(null, new JsonBehavior(JsonBehaviorKind::Null))), $derivation->environment());
        self::assertEquals(new Known(Builtin::Bool), $fact->type);
        self::assertSame('invalid ON ERROR behavior', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesEveryClauseInOrder(): void
    {
        $query = new JsonQueryFunction(JsonFunctionKind::Query, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$.a')), [new JsonArgument(new JsonValueExpression(new Constant(new IntegerConstant('1'))), new Name('x'))], new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')])))), new JsonWrapping(JsonWrapperKind::Unconditional), new JsonQuoting(true), new JsonBehaviorClause(null, new JsonBehavior(JsonBehaviorKind::Error)));
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $query->render($out);
        self::assertSame('JSON_QUERY(\'{}\', \'$.a\' PASSING 1 AS x RETURNING text WITH WRAPPER KEEP QUOTES ERROR ON ERROR)', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAPathThatWouldTakeTheWrapper(): void
    {
        $this->expectExceptionMessage('A path ending in IS JSON without a uniqueness clause would take the wrapper clause; group it.');
        new JsonQueryFunction(JsonFunctionKind::Query, new JsonValueExpression(new Constant(new StringConstant('{}'))), new JsonTest(new Constant(new StringConstant('$.a')), false, JsonItemKind::Json), [], null, new JsonWrapping(JsonWrapperKind::Without));
    }
}
