<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class JsonTableColumnTest extends TestCase
{
    public function testAJsonTableColumnIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(JsonTableColumn::class));
        self::assertContains(Node::class, class_implements(JsonTableColumn::class));
    }
}
