<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Cast;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TypeName::class)]
#[Medium]
final class TypeNameTest extends TestCase
{
    public function testTextJoinsBareWordsWithOneSpace(): void
    {
        $type = new TypeName([new Word(new Name('UNSIGNED')), new Word(new Name('BIG')), new Word(new Name('INT'))], [new SignedNumber(null, new IntegerLiteral('10'))]);

        self::assertSame('UNSIGNED BIG INT', $type->text());
        self::assertSame('varchar', (new TypeName([new Word(new Name('varchar'))]))->text());
    }

    public function testTextKeepsOnlyTheFirstWordWhenItIsQuoted(): void
    {
        $single = new TypeName([new Word(new Name('x'), WordQuote::Single), new Word(new Name('INT'))]);
        $double = new TypeName([new Word(new Name('integer'), WordQuote::Double), new Word(new Name('x'), WordQuote::Double)]);
        $bracket = new TypeName([new Word(new Name('big int'), WordQuote::Bracket)]);

        self::assertSame('x', $single->text());
        self::assertSame('integer', $double->text());
        self::assertSame('big int', $bracket->text());
    }

    public function testTextKeepsTheQuotesOfAWordThatIsNotFirst(): void
    {
        $type = new TypeName([new Word(new Name('INT')), new Word(new Name('x'), WordQuote::Double), new Word(new Name("it's"), WordQuote::Single)]);

        self::assertSame('INT "x" \'it\'\'s\'', $type->text());
    }

    public function testAffinityFollowsTheOrderedSubstringRules(): void
    {
        $affinities = array_map(
            static fn (string $word): Affinity => (new TypeName([new Word(new Name($word))]))->affinity(),
            ['INT', 'BIGINT', 'CHARINT', 'VARCHAR', 'CLOB', 'TEXT', 'BLOB', 'REAL', 'FLOAT', 'DOUBLE', 'DECIMAL', 'BOOLEAN', 'DATE', 'STRING'],
        );

        self::assertSame([
            Affinity::Integer, Affinity::Integer, Affinity::Integer,
            Affinity::Text, Affinity::Text, Affinity::Text,
            Affinity::Blob,
            Affinity::Real, Affinity::Real, Affinity::Real,
            Affinity::Numeric, Affinity::Numeric, Affinity::Numeric, Affinity::Numeric,
        ], $affinities);
    }

    public function testAffinityReadsTheWholeTextSoFloatingPointIsInteger(): void
    {
        $type = new TypeName([new Word(new Name('FLOATING')), new Word(new Name('POINT'))]);

        self::assertSame(Affinity::Integer, $type->affinity());
        self::assertSame(Affinity::Text, (new TypeName([new Word(new Name('NATIONAL')), new Word(new Name('CHARACTER'))]))->affinity());
    }

    public function testAffinityIgnoresTheCaseAndTheArguments(): void
    {
        $decimal = new TypeName([new Word(new Name('decimal'))], [new SignedNumber(null, new IntegerLiteral('10')), new SignedNumber(NumberSign::Minus, new IntegerLiteral('5'))]);
        $varchar = new TypeName([new Word(new Name('varchar'))], [new SignedNumber(null, new IntegerLiteral('255'))]);

        self::assertSame(Affinity::Numeric, $decimal->affinity());
        self::assertSame(Affinity::Text, $varchar->affinity());
        self::assertSame(Affinity::Integer, (new TypeName([new Word(new Name('integer'))]))->affinity());
    }

    public function testAffinityOfAQuotedFirstWordUsesThatWordOnly(): void
    {
        $shadowed = new TypeName([new Word(new Name('x'), WordQuote::Single), new Word(new Name('INT'))]);
        $quoted = new TypeName([new Word(new Name('INTEGER'), WordQuote::Double), new Word(new Name('TEXT'))]);

        self::assertSame(Affinity::Numeric, $shadowed->affinity());
        self::assertSame(Affinity::Integer, $quoted->affinity());
    }

    public function testRenderWritesTheWordsAndTheArgumentsWithoutSpaces(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $spaced = $semantics->analyze('select cast(a as decimal ( + 10 , -2 )) from t');
        $quoted = $semantics->analyze('SELECT CAST(a AS "big" int), CAST(a AS unsigned big int(3)) FROM t');
        $cast = $spaced->field(0)->expression;

        self::assertInstanceOf(Cast::class, $cast);
        self::assertNotNull($cast->target);
        self::assertSame('decimal', $cast->target->text());
        self::assertCount(2, $cast->target->arguments);
        self::assertSame('SELECT CAST(a AS decimal(+10,-2)) FROM t', $spaced->toString());
        self::assertSame('SELECT CAST(a AS "big" int), CAST(a AS unsigned big int(3)) FROM t', $quoted->toString());
    }

    public function testRefusesANameWithoutWords(): void
    {
        $this->expectExceptionMessage('A type name has at least one word.');

        new TypeName([]);
    }

    public function testRefusesMoreThanTwoArguments(): void
    {
        $this->expectExceptionMessage('A type name has at most two arguments.');

        new TypeName([new Word(new Name('DECIMAL'))], [new SignedNumber(null, new IntegerLiteral('1')), new SignedNumber(null, new IntegerLiteral('2')), new SignedNumber(null, new IntegerLiteral('3'))]);
    }
}
