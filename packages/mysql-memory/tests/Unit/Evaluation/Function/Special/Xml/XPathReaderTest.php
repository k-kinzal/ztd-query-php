<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(XPathReader::class)]
#[Small]
final class XPathReaderTest extends TestCase
{
    public function testSyntaxQuotesTheTextFromTheReader(): void
    {
        $reader = new XPathReader('/a  b');
        $reader->at = 2;

        self::assertSame("XPATH syntax error: 'b'", $reader->syntax()->getMessage());
    }

    public function testBlankSkipsWhiteSpace(): void
    {
        $reader = new XPathReader("  \n/");
        $reader->blank();

        self::assertSame(3, $reader->at);
    }

    public function testPeekReadsTheNextToken(): void
    {
        self::assertSame([['//', '//', 0], ['NUMBER', '1.50', 1], ['AXIS', 'child', 0], ['NAME', 'b:c', 0], ['STRING', 'x', 1], ['END', '', 1], ['UNKNOWN', '#', 0]], [(new XPathReader('//a'))->peek(), (new XPathReader(' 1.50'))->peek(), (new XPathReader('child::a'))->peek(), (new XPathReader('b:c'))->peek(), (new XPathReader(' "x"'))->peek(), (new XPathReader(' '))->peek(), (new XPathReader('#'))->peek()]);
    }

    public function testPeekRefusesAnUnknownAxis(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: ':a'");

        (new XPathReader('bogus::a'))->peek();
    }

    public function testPeekRefusesAStringThatIsNotClosed(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: ''x'");

        (new XPathReader("'x"))->peek();
    }

    public function testNextReadsPastTheToken(): void
    {
        $reader = new XPathReader('child::a');
        $reader->next();

        self::assertSame(7, $reader->at);
    }

    public function testAfterAnswersTheCharacterAfterAName(): void
    {
        self::assertSame(['(', ''], [(new XPathReader('count (x)'))->after('count'), (new XPathReader('count'))->after('count')]);
    }
}
