<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Mutation\InsertCell;

#[CoversClass(InsertCell::class)]
#[Small]
final class InsertCellTest extends TestCase
{
    public function testToStringDistinguishesIntegerAndNull(): void
    {
        self::assertSame('-2', (new InsertCell(new IntegerLiteral('-2')))->toString());
        self::assertSame('NULL', (new InsertCell(null))->toString());
    }

    public function testToStringKeepsTheExactQuotedTextBytes(): void
    {
        $cell = new InsertCell(new TextLiteral("a'𐐷 "));

        self::assertSame("'a''𐐷 '", $cell->toString());
    }
}
