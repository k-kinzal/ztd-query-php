<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Hint\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;

#[CoversClass(HintTable::class)]
#[Small]
final class HintTableTest extends TestCase
{
    public function testTextQuotesTheTableAndItsBlock(): void
    {
        self::assertSame('`t1`', (new HintTable('t1'))->text());
        self::assertSame('`a``b`@`my qb`', (new HintTable('a`b', 'my qb'))->text());
    }

    public function testQuoteDoublesBackticks(): void
    {
        self::assertSame('```x`', HintTable::quote('`x'));
    }

    public function testAnEmptyNameIsRefused(): void
    {
        $this->expectExceptionMessage('A table and a query block of a hint have a name.');

        new HintTable('t', '');
    }
}
