<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special\Xml;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Special\Xml\XmlDocument;
use MySqlMemory\Evaluation\Function\Special\Xml\XPathOperand;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(XPathOperand::class)]
#[Small]
final class XPathOperandTest extends TestCase
{
    public function testNodesSortsWithoutRepeats(): void
    {
        self::assertSame([1, 3], XPathOperand::nodes([3, 1, 3])->nodes);
    }

    public function testTruthIsOneOrZero(): void
    {
        self::assertSame([1, 0], [XPathOperand::truth(true)->value, XPathOperand::truth(false)->value]);
    }

    public function testNumericTellsNumbersApart(): void
    {
        self::assertSame([true, true, false], [(new XPathOperand(XPathOperand::INTEGER, 1))->numeric(), XPathOperand::truth(true)->numeric(), (new XPathOperand(XPathOperand::STRING, '1'))->numeric()]);
    }

    public function testTextWritesEachKind(): void
    {
        $document = XmlDocument::read('<a>x</a><a>y</a>');

        self::assertInstanceOf(XmlDocument::class, $document);
        self::assertSame(['x y', '7', '2.2', '3', 'q', null], [
            XPathOperand::nodes([1, 3])->text($document),
            (new XPathOperand(XPathOperand::INTEGER, 7))->text($document),
            (new XPathOperand(XPathOperand::DOUBLE, 2.25, [], 1))->text($document),
            (new XPathOperand(XPathOperand::DOUBLE, 3.0))->text($document),
            (new XPathOperand(XPathOperand::STRING, 'q'))->text($document),
            (new XPathOperand(XPathOperand::NULL))->text($document),
        ]);
    }

    public function testFixedKeepsTheSignOfANegativeZero(): void
    {
        self::assertSame(['-0', '1.50'], [(new XPathOperand(XPathOperand::DOUBLE, -0.0, [], 0))->fixed(-0.0), (new XPathOperand(XPathOperand::DOUBLE, 1.5, [], 2))->fixed(1.5)]);
    }

    public function testRealWarnsOfAStringThatIsNoNumber(): void
    {
        $session = (new Instance())->connect();
        $diagnostics = $session->diagnostics;
        $frame = new Frame(new Context($session->modes(), $diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');

        self::assertSame([1.5, 0.0, 1], [(new XPathOperand(XPathOperand::DOUBLE, 1.5))->real($document, $frame, Collation::known('utf8mb4_0900_ai_ci')), (new XPathOperand(XPathOperand::STRING, 'x'))->real($document, $frame, Collation::known('utf8mb4_0900_ai_ci')), count($diagnostics->conditions)]);
    }

    public function testHoldsTellsTheTruthOfAValue(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $document = new XmlDocument('');
        $collation = Collation::known('utf8mb4_0900_ai_ci');

        self::assertSame([true, false, false, false, null], [XPathOperand::nodes([0])->holds($document, $frame, $collation), XPathOperand::nodes([])->holds($document, $frame, $collation), XPathOperand::nodes([0, 1])->holds($document, $frame, $collation), (new XPathOperand(XPathOperand::STRING, '0'))->holds($document, $frame, $collation), (new XPathOperand(XPathOperand::NULL))->holds($document, $frame, $collation)]);
    }
}
