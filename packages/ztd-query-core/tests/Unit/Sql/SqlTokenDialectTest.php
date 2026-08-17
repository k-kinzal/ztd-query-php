<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Sql\SqlTokenDialect;

#[CoversClass(SqlTokenDialect::class)]
final class SqlTokenDialectTest extends TestCase
{
    public function testDialectsHaveStableValues(): void
    {
        self::assertSame('standard', SqlTokenDialect::Standard->value);
        self::assertSame('mysql', SqlTokenDialect::MySql->value);
    }
}
