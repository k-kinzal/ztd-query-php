<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class WindowSpecificationTest extends TestCase
{
    public function testAWindowSpecificationIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(WindowSpecification::class));
        self::assertContains(Node::class, class_implements(WindowSpecification::class));
    }
}
