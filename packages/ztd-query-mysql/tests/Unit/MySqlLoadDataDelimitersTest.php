<?php

declare(strict_types=1);

namespace Tests\Unit;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLoadDataDelimiters;

#[CoversClass(MySqlLoadDataDelimiters::class)]
final class MySqlLoadDataDelimitersTest extends TestCase
{
    public function testOptionValueAnswersWhatTheStatementSetItTo(): void
    {
        $statement = (new Parser("LOAD DATA INFILE 'f' INTO TABLE t FIELDS TERMINATED BY ','"))->statements[0];
        self::assertInstanceOf(LoadStatement::class, $statement);

        self::assertSame(
            ',',
            (new MySqlLoadDataDelimiters())->optionValue($statement->fields_options, 'TERMINATED BY', "\t"),
        );
    }

    public function testOptionValueFallsBackToWhatMySqlWouldUse(): void
    {
        self::assertSame("\t", (new MySqlLoadDataDelimiters())->optionValue(null, 'TERMINATED BY', "\t"));
    }

    #[DataProvider('providerEscapedBytes')]
    public function testEscapedByteAnswersTheByteTheEscapeStandsFor(string $written, string $stands): void
    {
        self::assertSame($stands, (new MySqlLoadDataDelimiters())->escapedByte($written));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerEscapedBytes(): iterable
    {
        yield 'nul' => ['0', "\0"];
        yield 'backspace' => ['b', "\x08"];
        yield 'newline' => ['n', "\n"];
        yield 'carriage return' => ['r', "\r"];
        yield 'tab' => ['t', "\t"];
        yield 'substitute' => ['Z', "\x1a"];
        yield 'anything else is itself' => [',', ','];
    }

    public function testRecordsSplitsTheFileWhereTheTerminatorSaysTo(): void
    {
        self::assertSame(
            ['a,b', 'c,d'],
            (new MySqlLoadDataDelimiters())->records("a,b\nc,d\n", ',', "\n", '', '\\'),
        );
    }

    public function testRecordsEndsTheLastRecordAtTheEndOfTheFile(): void
    {
        self::assertSame(['a,b'], (new MySqlLoadDataDelimiters())->records('a,b', ',', "\n", '', '\\'));
    }

    public function testRecordsKeepsATerminatorInsideSomethingWrapped(): void
    {
        self::assertSame(
            ["\"a\nb\""],
            (new MySqlLoadDataDelimiters())->records("\"a\nb\"\n", ',', "\n", '"', '\\'),
        );
    }

    public function testRecordsKeepsAnEscapedTerminator(): void
    {
        self::assertSame(["a\\\nb"], (new MySqlLoadDataDelimiters())->records("a\\\nb", ',', "\n", '', '\\'));
    }

    public function testFieldsSplitsARecordWhereTheTerminatorSaysTo(): void
    {
        self::assertSame(['a', 'b'], (new MySqlLoadDataDelimiters())->fields('a,b', ',', '', '\\'));
    }

    public function testFieldsReadsAnEscapeAsWhatItStandsFor(): void
    {
        self::assertSame(["a\tb"], (new MySqlLoadDataDelimiters())->fields('a\\tb', ',', '', '\\'));
    }

    public function testFieldsUnwrapsAFieldThatWasWrapped(): void
    {
        self::assertSame(['a,b'], (new MySqlLoadDataDelimiters())->fields('"a,b"', ',', '"', '\\'));
    }

    public function testFieldsReadsADoubledWrapperAsOneOfIt(): void
    {
        self::assertSame(['a"b'], (new MySqlLoadDataDelimiters())->fields('"a""b"', ',', '"', '\\'));
    }

    public function testFieldValueReadsTheEscapedNAsNoValueAtAll(): void
    {
        self::assertNull((new MySqlLoadDataDelimiters())->fieldValue('\\N', '\\N', false, '', '\\'));
    }

    public function testFieldValueReadsAnUnwrappedNullAsNoValueAtAll(): void
    {
        self::assertNull((new MySqlLoadDataDelimiters())->fieldValue('NULL', 'NULL', false, '', ''));
    }

    public function testFieldValueReadsAWrappedNullAsThoseFourLetters(): void
    {
        self::assertSame('NULL', (new MySqlLoadDataDelimiters())->fieldValue('NULL', 'NULL', true, '"', '\\'));
    }

    public function testFieldValueAnswersWhatTheFieldSaysOtherwise(): void
    {
        self::assertSame('a', (new MySqlLoadDataDelimiters())->fieldValue('a', 'a', false, '', '\\'));
    }

    public function testStartsAtReportsSomethingWrittenAtExactlyThatPosition(): void
    {
        self::assertTrue((new MySqlLoadDataDelimiters())->startsAt('abcd', 'bc', 1));
    }

    public function testStartsAtIsFalseWhereItIsWrittenSomewhereElse(): void
    {
        self::assertFalse((new MySqlLoadDataDelimiters())->startsAt('abcd', 'bc', 2));
    }

    public function testByteAfterAnswersTheNextByte(): void
    {
        self::assertSame('b', (new MySqlLoadDataDelimiters())->byteAfter('ab', 0));
    }

    public function testByteAfterIsNothingAtTheEndOfTheText(): void
    {
        self::assertNull((new MySqlLoadDataDelimiters())->byteAfter('ab', 1));
    }
}
