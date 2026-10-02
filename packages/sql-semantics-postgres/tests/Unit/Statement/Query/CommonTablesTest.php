<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;

#[CoversClass(CommonTables::class)]
#[Small]
final class CommonTablesTest extends TestCase
{
    public function testDeriveCommonTablesIsTheContractOfAWithClause(): void
    {
        self::assertTrue(method_exists(CommonTables::class, 'deriveCommonTables'));
    }
}
