<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::class)]
final class NativeCastTargetTest extends TestCase
{
    public function testIntegerPreservesWidths(): void
    {
        self::assertSame('SMALLINT', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::integer('int2'));
        self::assertSame('BIGINT', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::integer('BIGSERIAL'));
        self::assertSame('INTEGER', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::integer('INTEGER'));
    }

    public function testDecimalPreservesPrecision(): void
    {
        self::assertSame('NUMERIC(10,2)', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::decimal('decimal(10,2)'));
        self::assertSame('NUMERIC(8,0)', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::decimal('NUMERIC(8)'));
        self::assertSame('NUMERIC', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::decimal('DECIMAL'));
    }

    public function testStringPreservesBounds(): void
    {
        self::assertSame('VARCHAR(20)', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::string('varchar(20)'));
        self::assertSame('VARCHAR', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::string('CHARACTER VARYING'));
        self::assertSame('TEXT', \ZtdQuery\Platform\Postgres\Sql\NativeCastTarget::string('CHAR(5)'));
    }
}
