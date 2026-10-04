<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\TransactionScope::class)]
#[Medium]
final class TransactionScopeTest extends TestCase
{
    public function testBothScopesAreKept(): void
    {
        self::assertSame(['SET TRANSACTION READ ONLY', 'SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TRANSACTION READ ONLY')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY')->toString()]);
    }
}
