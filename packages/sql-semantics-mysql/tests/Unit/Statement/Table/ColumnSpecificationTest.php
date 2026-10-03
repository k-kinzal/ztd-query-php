<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class ColumnSpecificationTest extends TestCase
{
    public function testAColumnSpecificationIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(ColumnSpecification::class));
        self::assertContains(Node::class, class_implements(ColumnSpecification::class));
    }
}
