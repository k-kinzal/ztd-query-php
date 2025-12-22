<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use ZtdQuery\Platform\MySql\MySqlDialect;
use ZtdQuery\Platform\SqlDialect;
use PHPUnit\Framework\TestCase;

final class MySqlDialectTest extends TestCase
{
    public function testImplementsSqlDialect(): void
    {
        $dialect = new MySqlDialect();

        $this->assertInstanceOf(SqlDialect::class, $dialect);
    }

    public function testParseAndEmitRoundTrip(): void
    {
        $dialect = new MySqlDialect();
        $statements = $dialect->parse('SELECT 1');

        $this->assertCount(1, $statements);
        $sql = $dialect->emit($statements[0]);

        $this->assertStringContainsString('SELECT 1', $sql);
    }
}
