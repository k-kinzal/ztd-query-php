<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathFunctions;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathOperand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(XPathFunctions::class)]
#[Small]
final class XPathFunctionsTest extends TestCase
{
    public function testApplyComputesTheFunctions(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a>1</a><a>x</a>', 'sum(/a)'), EXTRACTVALUE('<a/>', 'concat(\"a\",\"b\",\"c\")'), EXTRACTVALUE('<a/>', 'round(2.5)'), EXTRACTVALUE('<a/>', 'ceiling(-0.5)'), EXTRACTVALUE('<a>é</a>', 'string-length(/a)'), EXTRACTVALUE('<a/>', 'number()'), EXTRACTVALUE('<a/>', 'boolean(\"0\")')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', 'ab', '2', '-0', '1', '0', '0']], $result->rows);
    }

    public function testApplyAnswersThePositionAndTheSize(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');

        self::assertSame([2, 5, 1], [XPathFunctions::apply('position', [], $document, $frame, Collation::binary(), 2, 5)->value, XPathFunctions::apply('last', [], $document, $frame, Collation::binary(), 2, 5)->value, XPathFunctions::apply('true', [], $document, $frame, Collation::binary(), 2, 5)->value]);
    }

    public function testSumAddsTheNumbersTheTextsStartWith(): void
    {
        $document = XmlDocument::read('<a>1.5x</a><a>2</a><a>y</a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame(3.5, XPathFunctions::sum(XPathOperand::nodes([1, 3, 5]), $document)->value);
    }

    public function testLogicalAnswersNullForAnUnknownTruth(): void
    {
        self::assertSame([0, 1, XPathOperand::NULL], [XPathFunctions::logical('not', true)->value, XPathFunctions::logical('boolean', true)->value, XPathFunctions::logical('not', null)->kind]);
    }

    public function testNumericRoundsToTheNearestEven(): void
    {
        self::assertSame([2.0, -1.0, 1.0, 0, 1.5, XPathOperand::NOT_FIXED, XPathOperand::NULL], [XPathFunctions::numeric('round', 2.5)->value, XPathFunctions::numeric('floor', -0.5)->value, XPathFunctions::numeric('ceiling', 0.5)->value, XPathFunctions::numeric('ceiling', 0.5)->decimals, XPathFunctions::numeric('number', 1.5)->value, XPathFunctions::numeric('number', 1.5)->decimals, XPathFunctions::numeric('floor', null)->kind]);
    }

    public function testTextualAnswersNullForANullArgument(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');

        self::assertSame([XPathOperand::NULL, 'ab', 1], [XPathFunctions::textual('contains', [new XPathOperand(XPathOperand::NULL), new XPathOperand(XPathOperand::STRING, 'a')], $document, $frame, Collation::binary())->kind, XPathFunctions::textual('concat', [new XPathOperand(XPathOperand::STRING, 'a'), new XPathOperand(XPathOperand::STRING, 'b'), new XPathOperand(XPathOperand::NULL)], $document, $frame, Collation::binary())->value, XPathFunctions::textual('contains', [new XPathOperand(XPathOperand::STRING, 'abc'), new XPathOperand(XPathOperand::STRING, 'b')], $document, $frame, Collation::binary())->value]);
    }

    public function testPrefixReadsTheNumberATextStartsWith(): void
    {
        self::assertSame([12.0, 0.0], [XPathFunctions::prefix('12abc'), XPathFunctions::prefix('x')]);
    }

    public function testContainsComparesAsTheCollation(): void
    {
        self::assertSame([true, false, true], [XPathFunctions::contains('ABC', 'b', Collation::known('utf8mb4_0900_ai_ci')), XPathFunctions::contains('ABC', 'b', Collation::known('utf8mb4_bin')), XPathFunctions::contains('', '', Collation::binary())]);
    }

    public function testSubstringCountsAsSubstringDoes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a/>', 'substring(\"abcdef\", 0, 3)'), EXTRACTVALUE('<a/>', 'substring(\"abcdef\", -1)'), EXTRACTVALUE('<a/>', 'substring(\"abcdef\", 1.5, 2.5)')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['', 'f', 'bc']], $result->rows);
    }

    public function testVariableReadsAUserVariable(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET @n = 2, @s = 'x', @d = 1.5");
        $result = $session->query("SELECT EXTRACTVALUE('<a><b>1</b><b>2</b></a>', '//b[\$@n]'), EXTRACTVALUE('<a/>', '\$@d * 2'), EXTRACTVALUE('<a><b>1</b></a>', '//b[\$@s]'), EXTRACTVALUE('<a/>', '\$@nope')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[['2', '3.000000000000000000000000000000', '', null]], []], [$result->rows, $warnings->rows]);
    }
}
