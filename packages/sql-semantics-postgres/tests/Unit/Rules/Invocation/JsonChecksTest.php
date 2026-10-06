<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\JsonChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonFormat;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonReturning;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQueryFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonQuoting;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapperKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonWrapping;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(JsonChecks::class)]
#[Small]
final class JsonChecksTest extends TestCase
{
    public function testInputReportsAnEncodingOnANonByteaValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonChecks())->input($derivation, new JsonValueExpression(new Constant(new IntegerConstant('1')), new JsonFormat(new Name('utf8'))), new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull));
        self::assertEquals([new JsonProblem(JsonProblemKind::EncodingWithoutBytea)], $derivation->facts()->diagnostics);
    }

    public function testQueryReportsFormatInReturningAndQuotesWithWrapper(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonChecks())->query($derivation, new JsonQueryFunction(JsonFunctionKind::Query, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$')), [], null, new JsonWrapping(JsonWrapperKind::Unconditional), new JsonQuoting(false)));
        (new JsonChecks())->query($derivation, new JsonQueryFunction(JsonFunctionKind::Value, new JsonValueExpression(new Constant(new StringConstant('{}'))), new Constant(new StringConstant('$')), [], new JsonReturning(new TypeName(new NamedDesignation(new DottedName([new Name('text')]))), new JsonFormat())));
        self::assertEquals([new JsonProblem(JsonProblemKind::QuotesWithWrapper), new JsonProblem(JsonProblemKind::FormatInReturning, ['json_value'])], $derivation->facts()->diagnostics);
    }

    public function testBehaviorsNamesTheColumn(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new JsonChecks())->behaviors($derivation, JsonFunctionKind::Value, new JsonBehaviorClause(new JsonBehavior(JsonBehaviorKind::True), null), new Name('c'));
        self::assertSame('invalid ON EMPTY behavior for column "c"', $derivation->facts()->diagnostics[0]->message());
    }

    public function testAcceptedFollowsTheFunction(): void
    {
        $checks = new JsonChecks();
        self::assertSame([true, false, true], [$checks->accepted(JsonFunctionKind::Query, JsonBehaviorKind::EmptyObject), $checks->accepted(JsonFunctionKind::Value, JsonBehaviorKind::Empty), $checks->accepted(JsonFunctionKind::Exists, JsonBehaviorKind::Unknown)]);
    }
}
