<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class)]
#[Small]
final class PrivilegeTargetTest extends TestCase
{
    public function testObjectIsOfferedByEveryTarget(): void
    {
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RelationsTarget::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\TypesTarget::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\RoutinesTarget::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParametersTarget::class));
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\SchemaContentsTarget::class));
    }

    public function testDeriveTargetIsOfferedByEveryTarget(): void
    {
        self::assertContains(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeTarget::class, class_implements(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\LargeObjectsTarget::class));
    }
}
