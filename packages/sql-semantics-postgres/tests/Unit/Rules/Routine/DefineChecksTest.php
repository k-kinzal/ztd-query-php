<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\DefineChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(DefineChecks::class)]
#[Medium]
final class DefineChecksTest extends TestCase
{
    public function testDeriveAcceptsTheAlternativeSpellings(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame([[], []], [$semantics->analyze('CREATE AGGREGATE a (basetype = int4, SFUNC1 = f, stype1 = int4)')->facts->diagnostics, $semantics->analyze('CREATE OPERATOR + (rightarg = int4, procedure = f)')->facts->diagnostics]);
    }

    public function testDeriveReportsTheProblemsOfEachKind(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            [
                ['aggregate stype must be specified', 'aggregate sfunc must be specified'],
                ['type input function must be specified', 'type modifier output function is useless without a type modifier input function'],
                ['parameter "lc_collate" must be specified', 'parameter "lc_ctype" must be specified', 'ICU rules cannot be specified unless locale provider is ICU'],
                ['text search parser start method is required', 'text search parser lextypes method is required'],
                ['text search template lexize method is required'],
                ['text search template is required'],
                [],
            ],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE AGGREGATE a(int4) (initcond = 0)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TYPE t (output = o, typmod_out = m)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("CREATE COLLATION c (rules = '')")->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TEXT SEARCH PARSER p (gettoken = g, "end" = e)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TEXT SEARCH TEMPLATE t (init = i)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TEXT SEARCH DICTIONARY d (stopwords = english)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TYPE t')->facts->diagnostics),
            ],
        );
    }

    public function testRangeReportsTheMissingSubtype(): void
    {
        self::assertSame(['type attribute "subtype" is required'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), (new Semantics(Dialect::PostgreSql))->analyze('CREATE TYPE r AS RANGE (subtype_diff = float8mi)')->facts->diagnostics));
    }

    public function testPresentKeepsTheLastRecognizedAttributeOfEachName(): void
    {
        $last = new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument());
        self::assertSame(['hashes' => $last], (new DefineChecks())->present([new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument()), new Attribute(new Name('x'), null), $last]));
    }

    public function testOperatorTellsAMissingRightArgumentFromMissingArguments(): void
    {
        $checks = new DefineChecks();
        self::assertSame(
            [['operator function must be specified', 'operator argument types must be specified'], ['operator right argument type must be specified']],
            [array_map(static fn (AttributeProblem $problem): string => $problem->message(), $checks->operator([])), array_map(static fn (AttributeProblem $problem): string => $problem->message(), $checks->operator(['function' => new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument()), 'leftarg' => new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument())]))],
        );
    }

    public function testAggregateReportsMovingSerializationHypotheticalAndBaseType(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            [
                ['aggregate msfunc must not be specified without mstype', 'aggregate msspace must not be specified without mstype', 'only ordered-set aggregates can be hypothetical', 'basetype is redundant with aggregate input type specification', 'must specify both or neither of serialization and deserialization functions'],
                ['aggregate minvfunc must be specified when mstype is specified', 'aggregate input type must be specified'],
                [],
            ],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE AGGREGATE a(int4) (sfunc = f, stype = int4, msfunc = m, msspace = 4, hypothetical, basetype = int4, serialfunc = s)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE AGGREGATE a (sfunc = f, stype = int4, mstype = int4, msfunc = m)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE AGGREGATE a(int4 ORDER BY int4) (sfunc = f, stype = int4, hypothetical, msspace = 0)')->facts->diagnostics),
            ],
        );
        self::assertSame([], (new DefineChecks())->aggregate(['stype' => new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument()), 'sfunc' => new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument())], new AggregateArguments([])));
    }

    public function testBaseTypeReportsTheMissingOutputFunction(): void
    {
        self::assertSame(['type output function must be specified'], array_map(static fn (AttributeProblem $problem): string => $problem->message(), (new DefineChecks())->baseType(['input' => new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument())])));
    }

    public function testCollationFollowsTheProvider(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            [['conflicting or redundant options'], ['conflicting or redundant options'], ['nondeterministic collations not supported with this provider'], ['parameter "locale" must be specified'], []],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("CREATE COLLATION c (locale = 'C', lc_ctype = 'C')")->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("CREATE COLLATION c (from = \"C\", locale = 'C')")->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("CREATE COLLATION c (locale = 'C', deterministic = false)")->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE COLLATION c (provider = builtin)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze("CREATE COLLATION c (provider = icu, locale = 'und', deterministic = false, rules = '')")->facts->diagnostics),
            ],
        );
        self::assertSame([], (new DefineChecks())->collation(['provider' => new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument())], 1, GrammarRelease::PostgreSql166));
    }

    public function testProvidedReportsWhatOnlyIcuTakes(): void
    {
        $attribute = new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument());
        self::assertSame(['ICU rules cannot be specified unless locale provider is ICU'], array_map(static fn (AttributeProblem $problem): string => $problem->message(), (new DefineChecks())->provided(CollationProvider::Builtin, ['locale' => $attribute, 'rules' => $attribute])));
    }

    public function testSearchReportsAConfigurationWithParserAndCopy(): void
    {
        $checks = new DefineChecks();
        $attribute = new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument());
        self::assertSame(
            [['cannot specify both PARSER and COPY options'], ['text search parser is required']],
            [array_map(static fn (AttributeProblem $problem): string => $problem->message(), $checks->search(ObjectKind::TextSearchConfiguration, ['parser' => $attribute, 'copy' => $attribute])), array_map(static fn (AttributeProblem $problem): string => $problem->message(), $checks->search(ObjectKind::TextSearchConfiguration, []))],
        );
    }
}
