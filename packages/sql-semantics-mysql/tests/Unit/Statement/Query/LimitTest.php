<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class LimitTest extends TestCase
{
    public function testALimitIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(Limit::class));
        self::assertContains(Node::class, class_implements(Limit::class));
    }
}
