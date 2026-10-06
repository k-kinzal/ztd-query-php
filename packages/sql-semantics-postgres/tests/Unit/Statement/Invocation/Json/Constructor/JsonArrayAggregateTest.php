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
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Constructor\JsonArrayAggregate;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonNullHandling;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonArrayAggregate::class)]
#[Small]
final class JsonArrayAggregateTest extends TestCase
{
    public function testOutputNameIsJsonArrayagg(): void
    {
        self::assertSame('json_arrayagg', (new JsonArrayAggregate(new JsonValueExpression(new NullLiteral())))->outputName()->value);
    }

    public function testDeriveScalarDerivesTheOrdering(): void
    {
        $key = new Constant(new IntegerConstant('1'));
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = $derivation->scalar(new JsonArrayAggregate(new JsonValueExpression(new Constant(new IntegerConstant('1'))), [new SortItem($key)]), $derivation->environment());
        self::assertTrue($derivation->facts()->covers($key));
        self::assertEquals(new ScalarFact(new Known(Builtin::Json), Nullability::Nullable), $fact);
    }

    public function testRenderWritesTheOrderingAndTheWindow(): void
    {
        $aggregate = new JsonArrayAggregate(new JsonValueExpression(new Constant(new IntegerConstant('1'))), [new SortItem(new Constant(new IntegerConstant('1')))], JsonNullHandling::Absent, new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')])))), null, new WindowSpecification());
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        $aggregate->render($out);
        self::assertSame('JSON_ARRAYAGG(1 ORDER BY 1 ABSENT ON NULL RETURNING text) OVER ()', (new Lexical())->join($out->pieces()));
    }
}
