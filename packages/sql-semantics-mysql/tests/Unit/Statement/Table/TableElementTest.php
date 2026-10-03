<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class TableElementTest extends TestCase
{
    public function testATableElementIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(TableElement::class));
        self::assertContains(Node::class, class_implements(TableElement::class));
    }
}
