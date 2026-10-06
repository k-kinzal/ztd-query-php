<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;

#[CoversClass(ValueCountMismatch::class)]
#[Small]
final class ValueCountMismatchTest extends TestCase
{
    public function testMessageNamesTheRow(): void
    {
        self::assertSame("Column count doesn't match value count at row 3", (new ValueCountMismatch(2, 1, 3))->message());
        self::assertSame("Column count doesn't match value count", (new ValueCountMismatch(2, 1))->message());
    }

    public function testMessageRejectsRowZero(): void
    {
        $this->expectExceptionMessage('Rows are counted from 1.');

        new ValueCountMismatch(2, 1, 0);
    }
}
