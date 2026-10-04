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
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuseKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CallChecks::class)]
#[Small]
final class CallChecksTest extends TestCase
{
    public function testArgumentsReportsNamingMistakes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new CallChecks())->arguments($derivation, [new NamedArgument(new Name('a'), new Constant(new IntegerConstant('1'))), new NamedArgument(new Name('a'), new Constant(new IntegerConstant('1'))), new PositionalArgument(new Constant(new IntegerConstant('1')))]);
        self::assertEquals([new ArgumentProblem(ArgumentProblemKind::RepeatedName, new Name('a')), new ArgumentProblem(ArgumentProblemKind::PositionalAfterNamed, new Name('a'))], $derivation->facts()->diagnostics);
    }

    public function testMisuseReportsEveryClauseAPlainFunctionRejects(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $first = (new CallChecks())->misuse($derivation, new FunctionCall(new DottedName([new Name('upper')]), [new PositionalArgument(new Constant(new IntegerConstant('1')))], false, true, false, [], false, new BooleanLiteral(true)), 's');
        self::assertEquals(new CallMisuse(CallMisuseKind::DistinctOnPlainFunction, new Name('upper')), $first);
        self::assertCount(2, $derivation->facts()->diagnostics);
    }

    public function testMisuseReportsAParameterlessAggregateWithoutStar(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $first = (new CallChecks())->misuse($derivation, new FunctionCall(new DottedName([new Name('count')])), 'a');
        self::assertSame('count(*) must be used to call a parameterless aggregate function', $first?->message());
    }

    public function testMisuseAcceptsAWindowFunctionWithOver(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        self::assertNull((new CallChecks())->misuse($derivation, new FunctionCall(new DottedName([new Name('rank')]), over: new WindowSpecification()), 'w'));
    }
}
