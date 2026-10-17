<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use MySqlMemory\Evaluation\Function\Special\Xml\XPath;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathAxes;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathReader;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(XPathAxes::class)]
#[Small]
final class XPathAxesTest extends TestCase
{
    public function testWalkCountsAncestorPositionsWithRepeats(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXTRACTVALUE('<x><a>A<b>B</b></a><a>A2<b>B2</b></a></x>', '//b/ancestor::*[2]'), EXTRACTVALUE('<a>A<b>B<c>C</c></b></a>', '//c/ancestor::*[1]'), EXTRACTVALUE('<a><b><c>1</c></b><b><c>2</c><c>3</c></b></a>', '//c[last()-1]')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['A2', 'B', '3']], $result->rows);
    }

    public function testWalkWarnsOfAStringPosition(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = XmlDocument::read('<a><b/><b/></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([[], 2], [XPath::compile("/a/b['x']")($document, $frame, Collation::known('utf8mb4_0900_ai_ci'), 0, 1, 1)->nodes, count($session->diagnostics->conditions)]);
    }

    public function testFilterNumbersTheKeptNodesAgainWithinTheirGroup(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');

        self::assertSame([[3, 1, 2, 1], [6, 1, 2, 4]], XPathAxes::filter([[2, 1, 4, 1], [3, 2, 4, 1], [5, 1, 4, 4], [6, 2, 4, 4]], (new XPath(new XPathReader('')))->number('2')[1], $document, $frame, Collation::binary()));
    }

    public function testAxisKeepsTheNodesOfANodeTypeTest(): void
    {
        $document = XmlDocument::read('<a><b/></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([[1, 1, 1, 1]], XPathAxes::axis('child', '()', [1], $document));
    }

    public function testAxisReachesTheChildrenAndAttributes(): void
    {
        $document = XmlDocument::read('<a c="1"><b/>x<d/></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([[[4, 1, 2, 1], [6, 2, 2, 1]], [[2, 1, 1, 1]], [[1, 1, 1, 0]]], [XPathAxes::axis('following', '*', [1], $document), XPathAxes::axis('attribute', 'c', [1], $document), XPathAxes::axis('child', 'a', [0], $document)]);
    }

    public function testAncestorsCountDownFromTheAncestorsMet(): void
    {
        $document = XmlDocument::read('<a><b><c/></b><b/></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([[[1, 3, 3, 0], [2, 2, 3, 0]], [[1, 1, 1, 0]]], [XPathAxes::ancestors('ancestor', '*', [3, 4], $document), XPathAxes::ancestors('parent', '*', [2, 4], $document)]);
    }

    public function testMatchesTellsTheKindAndTheName(): void
    {
        $document = XmlDocument::read('<a b="1"/>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([true, true, false, false], [XPathAxes::matches(1, XmlDocument::ELEMENT, 'a', $document), XPathAxes::matches(2, XmlDocument::ATTRIBUTE, '*', $document), XPathAxes::matches(1, XmlDocument::ELEMENT, 'b', $document), XPathAxes::matches(2, XmlDocument::ELEMENT, '*', $document)]);
    }

    public function testDescendantsAnswersElementsInDocumentOrder(): void
    {
        $document = XmlDocument::read('<a><b><c/></b><d/></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([[0, 1, 2, 3, 4], [2, 3, 4]], [XPathAxes::descendants(0, true, '*', $document), XPathAxes::descendants(1, false, '*', $document)]);
    }
}
