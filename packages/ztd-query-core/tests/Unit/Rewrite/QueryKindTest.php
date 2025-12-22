<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use ZtdQuery\Rewrite\QueryKind;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueryKind::class)]
final class QueryKindTest extends TestCase
{
    public function testEnumValues(): void
    {
        self::assertSame('read', QueryKind::READ->value);
        self::assertSame('write_simulated', QueryKind::WRITE_SIMULATED->value);
        self::assertSame('ddl_simulated', QueryKind::DDL_SIMULATED->value);
    }

    public function testFromValue(): void
    {
        self::assertSame(QueryKind::READ, QueryKind::from('read'));
        self::assertSame(QueryKind::WRITE_SIMULATED, QueryKind::from('write_simulated'));
        self::assertSame(QueryKind::DDL_SIMULATED, QueryKind::from('ddl_simulated'));
    }
}
