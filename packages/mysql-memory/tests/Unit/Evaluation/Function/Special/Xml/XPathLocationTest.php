<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Special\Xml\XPath;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathLocation;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathReader;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(XPathLocation::class)]
#[Small]
final class XPathLocationTest extends TestCase
{
    public function testLocationResolvesRelativePathsFromTheRoot(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<a><b c=\"1\">X</b><b c=\"2\">Y</b></a>', 'a/b'), EXTRACTVALUE('x<a>y</a>z', '/')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['X Y', 'x z']], $result->rows);
    }

    public function testStepsStartWithADescendantStepForDoubleSlash(): void
    {
        self::assertSame(['descendant-or-self', '*', []], (new XPathLocation(new XPath(new XPathReader('//b'))))->steps()[0]);
    }

    public function testRelativeReadsEachStep(): void
    {
        self::assertSame([['child', 'a', []], ['descendant-or-self', '*', []], ['parent', '*', []]], (new XPathLocation(new XPath(new XPathReader('a//..'))))->relative());
    }

    public function testStepReadsANodeTypeTest(): void
    {
        self::assertSame([['attribute', '()', []], ['self', '*', []], ['attribute', 'b', []]], [(new XPathLocation(new XPath(new XPathReader('attribute::node()'))))->step(), (new XPathLocation(new XPath(new XPathReader('.'))))->step(), (new XPathLocation(new XPath(new XPathReader('@b'))))->step()]);
    }

    public function testTestReadsANameOrAWildcard(): void
    {
        self::assertSame(['*', 'text', '()'], [(new XPathLocation(new XPath(new XPathReader('*'))))->test(), (new XPathLocation(new XPath(new XPathReader('text'))))->test(), (new XPathLocation(new XPath(new XPathReader('comment ( )'))))->test()]);
    }

    public function testTestRefusesANodeTypeTestWithArguments(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: 'x)'");

        (new XPathLocation(new XPath(new XPathReader('text(x)'))))->test();
    }

    public function testPredicatesReadEachPredicateInsideThePredicateDepth(): void
    {
        $parser = new XPath(new XPathReader('[1][last()]'));

        self::assertSame([2, 0], [count((new XPathLocation($parser))->predicates()), $parser->depth]);
    }

    public function testPredicatesRefuseAPredicateThatIsNotClosed(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("XPATH syntax error: ''");

        (new XPathLocation(new XPath(new XPathReader('[1'))))->predicates();
    }
}
