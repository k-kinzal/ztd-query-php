<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterOption;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class AlterOptionTest extends TestCase
{
    public function testAnAlterOptionIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(AlterOption::class));
        self::assertContains(Node::class, class_implements(AlterOption::class));
    }
}
