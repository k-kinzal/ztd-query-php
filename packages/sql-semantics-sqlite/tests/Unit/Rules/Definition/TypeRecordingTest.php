<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TypeRecording;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\NumberSign;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TypeRecording::class)]
#[Small]
final class TypeRecordingTest extends TestCase
{
    public function testDomainOfAnAbsentTypeIsNoDeclaredTypeWithBlobAffinity(): void
    {
        $domain = (new TypeRecording())->domain(null, false);

        self::assertSame('', $domain->declared);
        self::assertSame(Affinity::Blob, $domain->affinity);
    }

    public function testDomainRecordsTheWrittenTextWithItsArguments(): void
    {
        $type = new TypeName([new Word(new Name('UNSIGNED')), new Word(new Name('BIG')), new Word(new Name('INT'))], [new SignedNumber(NumberSign::Plus, new IntegerLiteral('10')), new SignedNumber(NumberSign::Minus, new IntegerLiteral('2'))]);
        $domain = (new TypeRecording())->domain($type, false);

        self::assertSame('UNSIGNED BIG INT(+10,-2)', $domain->declared);
        self::assertSame(Affinity::Integer, $domain->affinity);
        self::assertFalse($domain->standard);
    }

    public function testDomainRecognizesAStandardTypeAlsoWhenItIsOneQuotedWord(): void
    {
        $recording = new TypeRecording();

        self::assertTrue($recording->domain(new TypeName([new Word(new Name('integer'))]), false)->rowidCapable());
        self::assertTrue($recording->domain(new TypeName([new Word(new Name('INTEGER'), WordQuote::Double)]), false)->rowidCapable());
        self::assertTrue($recording->domain(new TypeName([new Word(new Name('text'), WordQuote::Single)]), true)->standard);
    }

    public function testDomainKeepsOnlyTheFirstQuotedRunOfATextThatStartsWithAQuote(): void
    {
        $recording = new TypeRecording();
        $leading = $recording->domain(new TypeName([new Word(new Name('x'), WordQuote::Single), new Word(new Name('INT'))]), false);
        $shadowed = $recording->domain(new TypeName([new Word(new Name('integer'), WordQuote::Double), new Word(new Name('x'), WordQuote::Double)]), false);

        self::assertSame('x', $leading->declared);
        self::assertSame(Affinity::Numeric, $leading->affinity);
        self::assertSame('integer', $shadowed->declared);
        self::assertFalse($shadowed->rowidCapable());
    }

    public function testDomainKeepsTheQuotesOfAWordThatIsNotFirst(): void
    {
        $domain = (new TypeRecording())->domain(new TypeName([new Word(new Name('INT')), new Word(new Name('x'), WordQuote::Double)]), false);

        self::assertSame('INT "x"', $domain->declared);
    }

    public function testDomainDropsTheGeneratedAlwaysKeywordsTheParserReadsAsTypeWords(): void
    {
        $recording = new TypeRecording();

        self::assertSame('INT', $recording->domain(new TypeName([new Word(new Name('INT')), new Word(new Name('GENERATED')), new Word(new Name('ALWAYS'))]), false)->declared);
        self::assertSame('', $recording->domain(new TypeName([new Word(new Name('generated')), new Word(new Name('always'))]), false)->declared);
        self::assertSame(Affinity::Blob, $recording->domain(new TypeName([new Word(new Name('generated')), new Word(new Name('always'))]), false)->affinity);
    }

    public function testDomainMarksAColumnOfAStrictTable(): void
    {
        self::assertTrue((new TypeRecording())->domain(new TypeName([new Word(new Name('ANY'))]), true)->strict);
    }

    public function testWrittenIsTheTextTheRenderedSqlSpells(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a  DECIMAL ( + 10 , -2 ), b "big" int)');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $first = $statement->columns[0]->type;
        $second = $statement->columns[1]->type;
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('CREATE TABLE t (a ' . (new TypeRecording())->written($first) . ', b ' . (new TypeRecording())->written($second) . ')', $operation->toString());
    }

    public function testStrippedDropsTheSuffixOnlyFromTextsOfSixteenCharactersOrMore(): void
    {
        $recording = new TypeRecording();

        self::assertSame('INT', $recording->stripped('INT GENERATED ALWAYS'));
        self::assertSame('', $recording->stripped('generated always'));
        self::assertSame('abcdefghijk', $recording->stripped('abcdefghijkalways'));
        self::assertSame('INT ALWAYS', $recording->stripped('INT ALWAYS'));
        self::assertSame('MY GENERATED ALWAYS(1)', $recording->stripped('MY GENERATED ALWAYS(1)'));
    }

    public function testQuotedWordHoldsForOneQuotedWordOnly(): void
    {
        $recording = new TypeRecording();

        self::assertTrue($recording->quotedWord('"INTEGER"'));
        self::assertTrue($recording->quotedWord('[big int]'));
        self::assertFalse($recording->quotedWord('"a" "b"'));
        self::assertFalse($recording->quotedWord('"a""b"'));
        self::assertFalse($recording->quotedWord('INTEGER'));
    }

    public function testFirstRunUnquotesUpToTheFirstClosingQuote(): void
    {
        $recording = new TypeRecording();

        self::assertSame('a', $recording->firstRun('"a" "b"'));
        self::assertSame('a"b', $recording->firstRun('"a""b" INT'));
        self::assertSame('a b', $recording->firstRun('[a b] INT'));
        self::assertSame("it's", $recording->firstRun("'it''s' x"));
    }
}
