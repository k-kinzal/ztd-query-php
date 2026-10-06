<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DropDatabaseOption::class)]
#[Medium]
final class DropDatabaseOptionTest extends TestCase
{
    public function testForceIsKept(): void
    {
        self::assertSame('DROP DATABASE d WITH (FORCE, FORCE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP DATABASE d WITH (FORCE, FORCE)')->toString());
    }
}
