<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleValidity::class)]
#[Medium]
final class RoleValidityTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('validUntil', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleValidity(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant('infinity')))->option());
    }

    public function testRenderWritesTheTime(): void
    {
        self::assertSame('CREATE ROLE r VALID UNTIL \'May 4 12:00:00 2030 +1\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE r VALID UNTIL \'May 4 12:00:00 2030 +1\'')->toString());
    }
}
