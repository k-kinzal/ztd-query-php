<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AttributeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Choice\CollationProvider;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\CollationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\DictionaryAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Define;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(AttributeChecks::class)]
#[Medium]
final class AttributeChecksTest extends TestCase
{
    public function testDeriveReportsUnrecognizedUnreadableAndRepeatedAttributes(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        self::assertSame(
            [['operator attribute "colour" not recognized', 'argument of function must be a name'], ['type attribute "foo" not recognized', 'conflicting or redundant options'], []],
            [
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE OPERATOR === (rightarg = int4, colour = red, function = 1)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TYPE t (input = i, output = o, foo, analyze = a, analyse = b)')->facts->diagnostics),
                array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('CREATE TEXT SEARCH DICTIONARY d (template = simple, stopwords = english, accept = false)')->facts->diagnostics),
            ],
        );
    }

    public function testUnrecognizedAnswersNothingForATemplateOption(): void
    {
        $checks = new AttributeChecks();
        self::assertSame([null, 'collation attribute "x" not recognized'], [$checks->unrecognized(DictionaryAttribute::class, new Attribute(new Name('x'), null)), $checks->unrecognized(CollationAttribute::class, new Attribute(new Name('x'), null))?->message()]);
    }

    public function testValueReportsWhatTheCommandChecksOnAReadValue(): void
    {
        $checks = new AttributeChecks();
        $builtin = new Attribute(new Name('provider'), CollationAttribute::Provider, new ChoiceArgument(new StringConstant('Builtin'), CollationProvider::Builtin));
        self::assertSame(['unrecognized collation provider: Builtin', null], [$checks->value($builtin, CollationAttribute::Provider, GrammarRelease::PostgreSql166)?->message(), $checks->value($builtin, CollationAttribute::Provider, GrammarRelease::PostgreSql172)?->message()]);
    }

    public function testValueReportsSetofInAnOperatorArgument(): void
    {
        self::assertSame('SETOF type not allowed for operator argument', (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR === (leftarg = SETOF int4, rightarg = int4, function = f)')->facts->diagnostics[0]->message());
    }

    public function testRejectedAnswersTheMessageOfEachReading(): void
    {
        $checks = new AttributeChecks();
        $number = new SignedNumber(false, new IntegerConstant('5'));
        $word = new StringConstant('odd');
        self::assertSame(
            ['x requires a parameter', 'x requires an integer value', 'argument of x must be a type name', 'x requires a Boolean value', 'x requires an integer value', 'invalid argument for x: "odd"', 'parameter "parallel" must be SAFE, RESTRICTED, or UNSAFE', 'parameter "x" must be READ_ONLY, SHAREABLE, or READ_WRITE', 'alignment "odd" not recognized', 'storage "odd" not recognized', 'unrecognized collation provider: odd', null],
            [
                $checks->rejected(Reading::Function, 'x', null)?->message(),
                $checks->rejected(Reading::Integer, 'x', null)?->message(),
                $checks->rejected(Reading::Type, 'x', $number)?->message(),
                $checks->rejected(Reading::Boolean, 'x', $word)?->message(),
                $checks->rejected(Reading::Length, 'x', $number)?->message(),
                $checks->rejected(Reading::Length, 'x', $word)?->message(),
                $checks->rejected(Reading::Parallelism, 'x', $word)?->message(),
                $checks->rejected(Reading::FinalModification, 'x', $word)?->message(),
                $checks->rejected(Reading::Alignment, 'x', $word)?->message(),
                $checks->rejected(Reading::Storage, 'x', $word)?->message(),
                $checks->rejected(Reading::Provider, 'x', $word)?->message(),
                $checks->rejected(Reading::Ignored, 'x', null)?->message(),
            ],
        );
    }

    public function testPrintableAcceptsOnlyPrintableAscii(): void
    {
        $checks = new AttributeChecks();
        self::assertSame([true, false, false, false], [$checks->printable('U'), $checks->printable(''), $checks->printable("\t"), $checks->printable("\u{e9}")]);
    }

    public function testDeriveIgnoresTheValueOfAnObsoleteOperatorAttribute(): void
    {
        self::assertSame([], (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR === (rightarg = int4, function = f, sort1 = 1, ltcmp)')->facts->diagnostics);
        $define = (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR === (rightarg = int4, function = f, sort1 = 1)')->statement;
        self::assertInstanceOf(Define::class, $define);
        self::assertSame(OperatorAttribute::Sort1, ($define->definition ?? [])[2]->known);
    }
}
