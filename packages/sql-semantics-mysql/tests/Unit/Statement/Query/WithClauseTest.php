<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Small]
final class WithClauseTest extends TestCase
{
    public function testAWithClauseIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(WithClause::class));
        self::assertContains(Node::class, class_implements(WithClause::class));
    }
}
