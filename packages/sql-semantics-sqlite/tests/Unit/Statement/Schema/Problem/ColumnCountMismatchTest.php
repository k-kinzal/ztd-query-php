<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\ColumnCountMismatch;

#[CoversClass(ColumnCountMismatch::class)]
#[Small]
final class ColumnCountMismatchTest extends TestCase
{
    public function testMessageGivesBothCounts(): void
    {
        self::assertSame('The column list names 1 columns but the query returns 2.', (new ColumnCountMismatch(1, 2))->message());
    }
}
