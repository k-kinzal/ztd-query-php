<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::class)]
final class ColumnSetTest extends TestCase
{
    public function testNormalizedColumns(): void
    {
        self::assertSame(['id', 'name', 'name'], \ZtdQuery\Platform\Postgres\Parsing\Conflict\ColumnSet::normalizedColumns(['NAME', 'id', 'Name']));
    }
}
