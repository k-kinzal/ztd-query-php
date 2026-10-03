<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class TableOptionTest extends TestCase
{
    public function testATableOptionIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(TableOption::class));
        self::assertContains(Node::class, class_implements(TableOption::class));
    }
}
