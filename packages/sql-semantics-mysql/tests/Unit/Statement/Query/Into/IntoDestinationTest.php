<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Into;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoOutfile;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class IntoDestinationTest extends TestCase
{
    public function testADestinationIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(IntoDestination::class));
        self::assertContains(Node::class, class_implements(IntoDestination::class));
        self::assertContains(IntoDestination::class, class_implements(IntoOutfile::class));
    }
}
