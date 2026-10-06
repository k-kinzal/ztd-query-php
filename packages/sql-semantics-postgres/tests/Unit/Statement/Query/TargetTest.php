<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Target;

#[CoversClass(Target::class)]
#[Small]
final class TargetTest extends TestCase
{
    public function testProjectIsOfferedByASelectListItem(): void
    {
        self::assertContains(Target::class, class_implements(ExpressionTarget::class));
    }
}
