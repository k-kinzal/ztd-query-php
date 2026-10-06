<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultScope::class)]
#[Small]
final class DefaultScopeTest extends TestCase
{
    public function testOptionIsOfferedByBothClauses(): void
    {
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultScope::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultScope::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles::class));
    }
}
