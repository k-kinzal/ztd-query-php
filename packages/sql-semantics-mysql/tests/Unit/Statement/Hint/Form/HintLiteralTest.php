<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;

#[CoversClass(HintLiteral::class)]
#[Small]
final class HintLiteralTest extends TestCase
{
    public function testTextWritesEachKind(): void
    {
        self::assertSame('1.50', (new HintLiteral(HintLiteralKind::Decimal, '1.50'))->text());
        self::assertSame('`16M`', (new HintLiteral(HintLiteralKind::Word, '16M'))->text());
        self::assertSame("'a''b'", (new HintLiteral(HintLiteralKind::Text, "a'b"))->text());
    }

    public function testAnIntegerWithLeadingZerosIsRefused(): void
    {
        $this->expectExceptionMessage('A value of a hint is a number, a word or a string that is not empty.');

        new HintLiteral(HintLiteralKind::Integer, '01');
    }

    public function testAnEmptyStringIsRefused(): void
    {
        $this->expectExceptionMessage('A value of a hint is a number, a word or a string that is not empty.');

        new HintLiteral(HintLiteralKind::Text, '');
    }
}
