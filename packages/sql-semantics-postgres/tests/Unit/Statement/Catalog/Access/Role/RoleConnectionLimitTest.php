<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit::class)]
#[Medium]
final class RoleConnectionLimitTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('connectionlimit', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('5'))))->option());
    }

    public function testAcceptableFromMinusOne(): void
    {
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))))->acceptable());
        self::assertTrue((new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('7'))))->acceptable());
        self::assertFalse((new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(true, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2'))))->acceptable());
    }

    public function testRenderWritesTheSignedLimit(): void
    {
        self::assertSame('CREATE ROLE r CONNECTION LIMIT - 1', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE r CONNECTION LIMIT -1')->toString());
    }

    public function testRejectsAFraction(): void
    {
        $this->expectExceptionMessage('A connection limit is an integer.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleConnectionLimit(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NumericConstant('1.5')));
    }
}
