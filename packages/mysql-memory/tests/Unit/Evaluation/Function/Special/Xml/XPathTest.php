<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use MySqlMemory\Evaluation\Function\Special\Xml\XPath;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathOperand;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathReader;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(XPath::class)]
#[Small]
final class XPathTest extends TestCase
{
    public function testCompileReadsTheAxesAndPredicates(): void
    {
        $session = (new Instance())->connect();
        $xml = '<a><b c="1">x</b><b c="2">y<d>q</d></b><e>z</e></a>';
        $result = $session->query("SELECT EXTRACTVALUE('$xml', '/a/*'), EXTRACTVALUE('$xml', '//b[@c=\"2\"]/d'), EXTRACTVALUE('$xml', '/a/b/@c'), EXTRACTVALUE('$xml', '//d/ancestor-or-self::*'), EXTRACTVALUE('$xml', '/a/b/following-sibling::*'), EXTRACTVALUE('$xml', 'count(//text())'), EXTRACTVALUE('$xml', '/a/b[last()-1]'), EXTRACTVALUE('$xml', '/a/b | /a/e')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['x y z', 'q', '1 2', 'y q', 'q', '6', 'x', 'x y z']], $result->rows);
    }

    public function testCompileRefusesAnExpressionThatDoesNotRead(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: '('x')'");

        XPath::compile("string('x')");
    }

    public function testExpressionComputesWithTheServersOperations(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a/>', '1 div 2'), EXTRACTVALUE('<a/>', '7 mod 3'), EXTRACTVALUE('<a/>', '1.5 * 1.5'), EXTRACTVALUE('<a/>', '1 + 1.25'), EXTRACTVALUE('<a/>', '-2.50'), EXTRACTVALUE('<a/>', '1 = 1 and 2 = 3 or 1'), EXTRACTVALUE('<a/>', '99999999999999999999'), EXTRACTVALUE('<a/>', '9223372036854775808')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', '2.2', '2.25', '-2.50', '1', '-1', '-9223372036854775808']], $result->rows);
    }

    public function testConjunctionTreatsNullAsSqlDoes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a/>', '\$@n and 0'), EXTRACTVALUE('<a/>', '\$@n or 1'), EXTRACTVALUE('<a/>', '\$@n and 1')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', '1', null]], $result->rows);
    }

    public function testMultiplicativeReadsDivAndMod(): void
    {
        $parser = new XPath(new XPathReader('7 div 2 mod 2'));
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(1, $parser->multiplicative()[1](new XmlDocument(''), $frame, Collation::binary(), 0, 1, 1)->value);
    }

    public function testComparisonRefusesTwoNodeSets(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH error: comparison of two nodesets is not supported: '= /a/d'");

        XPath::compile('/a/c = /a/d');
    }

    public function testAdditiveAndMultiplicativeKeepPrecedence(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a/>', '1 + 2 * 3'), EXTRACTVALUE('<a/>', '10 - 2 - 3'), EXTRACTVALUE('<a/>', '(1 + 2) * 3')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['7', '5', '9']], $result->rows);
    }

    public function testUnaryNegatesAndOverflowsAsBigint(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("BIGINT value is out of range in '-(-9223372036854775808)'");

        (new Instance())->connect()->query("SELECT EXTRACTVALUE('<a/>', '-9223372036854775808')");
    }

    public function testUnionRefusesASideThatIsNoNodeSet(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: '/a'");

        XPath::compile('1 | /a');
    }

    public function testPathFollowsAParenthesizedSetWithSteps(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a><b>x</b></a>', '(/a)/b'), EXTRACTVALUE('<a><b>x</b></a>', '((/a/b))')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['x', 'x']], $result->rows);
    }

    public function testPrimaryRefusesAVariableOfAStoredProgram(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown XPATH variable at: '\$x + 1'");

        XPath::compile('$x + 1');
    }

    public function testVariableReadsTheNameOfAUserVariable(): void
    {
        $reader = new XPathReader('$@n_1 + 1');
        $reader->at = 1;
        $read = (new XPath($reader))->variable(0);

        self::assertSame(['any', '@n_1', 5], [$read[0], $read[2], $reader->at]);
    }

    public function testVariableRefusesAUserVariableWithoutAName(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: ''");

        XPath::compile('$@');
    }

    public function testNumberReadsIntegersAndDoublesOfFixedDecimals(): void
    {
        $parser = new XPath(new XPathReader(''));

        self::assertSame([XPathOperand::INTEGER, XPathOperand::DOUBLE, '-1'], [$parser->number('7')[0], $parser->number('1.50')[0], $parser->number('18446744073709551616')[2]]);
    }

    public function testCallRefusesTheWrongArguments(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: '2)'");

        XPath::compile('not(1,2)');
    }
}
