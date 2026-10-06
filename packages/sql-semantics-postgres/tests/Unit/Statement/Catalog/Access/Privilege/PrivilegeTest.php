<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege::class)]
#[Medium]
final class PrivilegeTest extends TestCase
{
    public function testPrivilegeOfAName(): void
    {
        self::assertSame('insert', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(new \SqlSemantics\Statement\Identifier\Name('insert')))->privilege());
    }

    public function testPrivilegeOfAKeyword(): void
    {
        self::assertSame('references', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::References))->privilege());
    }

    public function testPrivilegeOfAllIsNull(): void
    {
        self::assertSame(null, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All, [new \SqlSemantics\Statement\Identifier\Name('a')]))->privilege());
    }

    public function testRenderWritesTheNameAndTheColumns(): void
    {
        self::assertSame('GRANT SELECT (a, "B"), "select", update ON TABLE t TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT select (a, "B"), "select", update ON t TO joe')->toString());
    }

    public function testRejectsAllWithoutColumns(): void
    {
        $this->expectExceptionMessage('ALL is a privilege of its own only with a column list.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All);
    }

    public function testRejectsColumnsOnAlterSystem(): void
    {
        $this->expectExceptionMessage('ALTER SYSTEM takes no column list.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::AlterSystem, [new \SqlSemantics\Statement\Identifier\Name('a')]);
    }
}
