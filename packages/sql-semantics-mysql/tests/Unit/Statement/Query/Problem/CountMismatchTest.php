<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;

#[CoversClass(CountMismatch::class)]
#[Small]
final class CountMismatchTest extends TestCase
{
    public function testMessageNamesBothLengths(): void
    {
        self::assertSame("Column count doesn't match value count (2 and 1).", (new CountMismatch(CountedList::ValueRows, 2, 1))->message());
    }
}
