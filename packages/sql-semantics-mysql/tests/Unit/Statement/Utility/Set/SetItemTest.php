<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetItem;

#[CoversClass(SetItem::class)]
#[Small]
final class SetItemTest extends TestCase
{
    public function testDeriveItemIsPartOfEveryItem(): void
    {
        self::assertTrue(interface_exists(SetItem::class));
        self::assertContains(\SqlSemantics\Statement\Node::class, class_implements(SetItem::class));
    }
}
