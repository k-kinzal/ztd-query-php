<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\Statement\PgSqlTableSampleMethod;

#[CoversClass(PgSqlTableSampleMethod::class)]
final class PgSqlTableSampleMethodTest extends TestCase
{
    public function testMethodsUsePostgreSqlKeywords(): void
    {
        self::assertSame('BERNOULLI', PgSqlTableSampleMethod::Bernoulli->value);
        self::assertSame('SYSTEM', PgSqlTableSampleMethod::System->value);
    }
}
