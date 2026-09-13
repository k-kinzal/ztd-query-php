<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Transformer\Insert\OrderedExpressions::class)]
final class OrderedExpressionsTest extends TestCase
{
    public function testOrderedValues(): void
    {
        self::assertSame(['b', 'a'], \ZtdQuery\Platform\Postgres\Transformer\Insert\OrderedExpressions::orderedValues([2 => 'b', 0 => 'a']));
    }
}
