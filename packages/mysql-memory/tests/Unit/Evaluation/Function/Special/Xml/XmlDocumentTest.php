<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmlDocument::class)]
#[Small]
final class XmlDocumentTest extends TestCase
{
    public function testReadAnswersTheNodesInDocumentOrder(): void
    {
        $document = XmlDocument::read('x<a b="1" c>y<![CDATA[<z>]]><!-- c --><d/></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([
            [XmlDocument::ROOT, '', -1, 0, 46],
            [XmlDocument::TEXT, 'x', 0, 0, 1],
            [XmlDocument::ELEMENT, 'a', 0, 1, 46],
            [XmlDocument::ATTRIBUTE, 'b', 2, 4, 9],
            [XmlDocument::TEXT, '1', 3, 4, 9],
            [XmlDocument::TEXT, 'y', 2, 12, 13],
            [XmlDocument::TEXT, '<z>', 2, 13, 28],
            [XmlDocument::ELEMENT, 'd', 2, 38, 42],
        ], $document->nodes);
    }

    public function testReadAnswersTheProblemOfAFragmentThatDoesNotRead(): void
    {
        self::assertSame([
            "parse error at line 1 pos 11: END-OF-INPUT unexpected ('>' wanted)",
            'parse error at line 1 pos 4: unexpected END-OF-INPUT',
            "parse error at line 1 pos 12: '</b>' unexpected (END-OF-INPUT wanted)",
            'parse error at line 1 pos 6: unknown token unexpected (ident or string wanted)',
            "parse error at line 3 pos 2: END-OF-INPUT unexpected ('>' wanted)",
            "parse error at line 1 pos 8: '<' unexpected ('?' wanted)",
        ], [XmlDocument::read('<a>c</a><b'), XmlDocument::read('<a>'), XmlDocument::read('<a>x</a></b>'), XmlDocument::read('<a b=1>'), XmlDocument::read("<a>\n<b\n"), XmlDocument::read('<a><?x</a>')]);
    }

    public function testParseClosesAnElementWhateverTheClosingName(): void
    {
        $document = new XmlDocument('<a>x</b>');

        self::assertSame([null, [XmlDocument::ELEMENT, 'a', 0, 0, 8]], [$document->parse(), $document->nodes[1]]);
    }

    public function testTagEndsTheSpanOneCharacterAfterTheClosingName(): void
    {
        $document = XmlDocument::read('<a><b></b ></a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame([3, 10], [$document->nodes[2][3], $document->nodes[2][4]]);
    }

    public function testCloseClosesTheOpenElementWhateverItsName(): void
    {
        $document = new XmlDocument('b >');
        $document->add(XmlDocument::ELEMENT, 'a', 0, 0, 0);
        $open = [0, 1];
        $root = [0];

        self::assertSame([null, [0], 2, "parse error at line 1 pos 2: '</a>' unexpected (END-OF-INPUT wanted)"], [$document->close($open), $open, $document->nodes[1][4], (new XmlDocument('a>'))->close($root)]);
    }

    public function testAttributesAddsTheAttributesAndAnswersTheTokenAfterThem(): void
    {
        $document = new XmlDocument(' b="x" c d=y/>');
        $document->add(XmlDocument::ELEMENT, 'a', 0, 0, 0);

        self::assertSame([['/', '/'], [XmlDocument::ATTRIBUTE, 'b', 1, 1, 6], [XmlDocument::TEXT, 'y', 4, 9, 12], "parse error at line 1 pos 4: '>' unexpected (ident or string wanted)"], [$document->attributes(1), $document->nodes[2], $document->nodes[5], (new XmlDocument('b=>'))->attributes(-1)]);
    }

    public function testEndOpensTheElementOrClosesAnEmptyOne(): void
    {
        $document = new XmlDocument('>');
        $open = [0];

        self::assertSame([null, [0, 1], null, 1, "parse error at line 1 pos 1: '>' unexpected ('?' wanted)"], [(new XmlDocument(''))->end('', '>', '>', 1, $open), $open, $document->end('', '/', '/', 0, $open), $document->nodes[0][4], (new XmlDocument(''))->end('?', '>', '>', -1, $open)]);
    }

    public function testTokenReadsTheTokensOfATag(): void
    {
        $document = new XmlDocument(' b = "x" 1');

        self::assertSame([['IDENT', 'b'], ['=', '='], ['STRING', '"x"'], ['unknown token', ''], 9], [$document->token(), $document->token(), $document->token(), $document->token(), $document->at]);
    }

    public function testAddAppendsAChild(): void
    {
        $document = new XmlDocument('');

        self::assertSame([1, [1]], [$document->add(XmlDocument::TEXT, 'x', 0, 0, 0), $document->children[0]]);
    }

    public function testUnexpectedNamesTheTokenAndWhatWasWanted(): void
    {
        self::assertSame(["parse error at line 1 pos 1: '=' unexpected ('>' wanted)", 'parse error at line 1 pos 1: IDENT unexpected (ident wanted)'], [(new XmlDocument(''))->unexpected('=', '=', "'>'"), (new XmlDocument(''))->unexpected('IDENT', 'a', 'ident')]);
    }

    public function testProblemCountsTheColumnFromTheLastNewline(): void
    {
        $document = new XmlDocument("ab\ncd");
        $document->at = 5;

        self::assertSame('parse error at line 2 pos 4: m', $document->problem('m'));
    }

    public function testTextsAnswersTheTextsDirectlyInNodes(): void
    {
        $document = XmlDocument::read('<a>x<b>y</b>z</a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame(['x', 'z', 'y'], $document->texts([1, 3]));
    }
}
