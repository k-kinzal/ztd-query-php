<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use MySqlMemory\Evaluation\Function\Special\Xml\XPath;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathOperand;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathOperators;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathReader;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(XPathOperators::class)]
#[Small]
final class XPathOperatorsTest extends TestCase
{
    public function testLogicCombinesTwoOperands(): void
    {
        $parser = new XPath(new XPathReader(''));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');

        self::assertSame([0, 1], [XPathOperators::logic($parser->number('1'), $parser->number('0'), true)[1]($document, $frame, Collation::binary(), 0, 1, 1)->value, XPathOperators::logic($parser->number('1'), $parser->number('0'), false)[1]($document, $frame, Collation::binary(), 0, 1, 1)->value]);
    }

    public function testLogicTreatsNullAsSqlDoes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a/>', '\$@n and 0'), EXTRACTVALUE('<a/>', '\$@n or 1'), EXTRACTVALUE('<a/>', '\$@n or 0')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', null]], $result->rows);
    }

    public function testCompareComparesThroughEachTextOfASet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a><b>x<c/>y</b></a>', '/a/b = \"y\"'), EXTRACTVALUE('<a><b>x<c/>y</b></a>', '/a/b = \"x y\"'), EXTRACTVALUE('<a/>', '\"abc\" = \"ABC\"'), EXTRACTVALUE('<a/>', '\"10\" < \"9\"'), EXTRACTVALUE('<a>3</a>', '/a = \"3.0\"'), EXTRACTVALUE('<a>3</a>', '/a = 3.0')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '0', '1', '1', '0', '1']], $result->rows);
    }

    public function testExactAnswersNullForAnOverflow(): void
    {
        self::assertSame([3, null], [XPathOperators::exact(3), XPathOperators::exact(1.0e19)]);
    }

    public function testNegateKeepsTheKindAndTheDecimals(): void
    {
        $parser = new XPath(new XPathReader(''));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');
        $integer = XPathOperators::negate($parser->number('7'));
        $double = XPathOperators::negate($parser->number('1.50'));

        self::assertSame([-7, '-7', -1.5, 2, '-1.50'], [$integer[1]($document, $frame, Collation::binary(), 0, 1, 1)->value, $integer[2], $double[1]($document, $frame, Collation::binary(), 0, 1, 1)->value, $double[1]($document, $frame, Collation::binary(), 0, 1, 1)->decimals, $double[2]]);
    }

    public function testArithmeticAnswersNullForADivisionByZero(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a/>', '5 div 0'), EXTRACTVALUE('<a>3</a>', '/a + 0.5')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[[null, '3.5']], [['Warning', '1365', 'Division by 0']]], [$result->rows, $warnings->rows]);
    }

    public function testArithmeticPrintsTheOperation(): void
    {
        $parser = new XPath(new XPathReader(''));

        self::assertSame('(7 % 2)', XPathOperators::arithmetic($parser->number('7'), $parser->number('2'), 'mod')[2]);
    }

    public function testIntegersComputesAsBigint(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame([3, -2, 0, PHP_INT_MAX, XPathOperand::NULL, 1], [XPathOperators::integers(7, 2, 'div', '?', $frame)->value, XPathOperators::integers(-7, 5, 'mod', '?', $frame)->value, XPathOperators::integers(PHP_INT_MIN, -1, 'mod', '?', $frame)->value, XPathOperators::integers(PHP_INT_MIN + 1, -1, 'div', '?', $frame)->value, XPathOperators::integers(1, 0, 'mod', '?', $frame)->kind, count($session->diagnostics->conditions)]);
    }

    public function testIntegersRefusesAnOverflow(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("BIGINT value is out of range in '(a * b)'");

        XPathOperators::integers(PHP_INT_MAX, 2, '*', '(a * b)', $frame);
    }

    public function testRealsKeepTheMostDecimals(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');
        $sum = XPathOperators::reals(new XPathOperand(XPathOperand::DOUBLE, 1.5, [], 1), new XPathOperand(XPathOperand::INTEGER, 2), '+', $document, $frame, Collation::binary());
        $quotient = XPathOperators::reals(new XPathOperand(XPathOperand::DOUBLE, 7.5, [], 1), new XPathOperand(XPathOperand::DOUBLE, 2.0, [], 0), 'div', $document, $frame, Collation::binary());

        self::assertSame([3.5, 1, XPathOperand::INTEGER, 3, XPathOperand::NULL], [$sum->value, $sum->decimals, $quotient->kind, $quotient->value, XPathOperators::reals(new XPathOperand(XPathOperand::NULL), $sum, '+', $document, $frame, Collation::binary())->kind]);
    }
}
