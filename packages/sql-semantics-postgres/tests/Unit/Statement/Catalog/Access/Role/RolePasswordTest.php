<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword::class)]
#[Medium]
final class RolePasswordTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('password', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword(null))->option());
    }

    public function testRenderDropsTheNoiseWordEncrypted(): void
    {
        self::assertSame('ALTER ROLE r PASSWORD \'x\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE r ENCRYPTED PASSWORD \'x\'')->toString());
    }

    public function testRenderWritesNull(): void
    {
        self::assertSame('ALTER ROLE r PASSWORD NULL', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE r PASSWORD NULL')->toString());
    }

    public function testRenderKeepsUnencrypted(): void
    {
        self::assertSame('ALTER ROLE r UNENCRYPTED PASSWORD \'x\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE r UNENCRYPTED PASSWORD \'x\'')->toString());
    }

    public function testRejectsUnencryptedWithoutAPassword(): void
    {
        $this->expectExceptionMessage('UNENCRYPTED PASSWORD is followed by a password.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RolePassword(null, true);
    }
}
