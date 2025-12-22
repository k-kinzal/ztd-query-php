<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use ZtdQuery\Rewrite\QueryKind;
use PHPUnit\Framework\TestCase;

final class QueryKindTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('read', QueryKind::READ->value);
        $this->assertSame('write_simulated', QueryKind::WRITE_SIMULATED->value);
        $this->assertSame('forbidden', QueryKind::FORBIDDEN->value);
    }

    public function testFromValue(): void
    {
        $this->assertSame(QueryKind::READ, QueryKind::from('read'));
        $this->assertSame(QueryKind::WRITE_SIMULATED, QueryKind::from('write_simulated'));
        $this->assertSame(QueryKind::FORBIDDEN, QueryKind::from('forbidden'));
    }
}
