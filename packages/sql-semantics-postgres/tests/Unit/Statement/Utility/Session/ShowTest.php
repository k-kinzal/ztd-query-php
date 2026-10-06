<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\Show::class)]
#[Medium]
final class ShowTest extends TestCase
{
    public function testDeriveStatementRecordsTheColumnsOfShowAll(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW ALL');
        self::assertSame(['name', 'setting', 'description'], [$operation->field(0)->name?->value, $operation->field(1)->name?->value, $operation->field(2)->name?->value]);
        self::assertSame([\SqlSemantics\Statement\Type\Nullability::NotNull, \SqlSemantics\Statement\Type\Nullability::Nullable], [$operation->field(0)->nullability, $operation->field(2)->nullability]);
    }

    public function testDeriveStatementNamesTheColumnOfAKeywordForm(): void
    {
        self::assertSame(['TimeZone', 'transaction_isolation', 'session_authorization'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW TIME ZONE')->field(0)->name?->value, (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW TRANSACTION ISOLATION LEVEL')->field(0)->name?->value, (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW SESSION AUTHORIZATION')->field(0)->name?->value]);
    }

    public function testDeriveStatementLeavesTheRowOfANamedParameterOpen(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW a.b');
        self::assertFalse($operation->shape()?->complete());
        self::assertSame('the session state: the registered name of the configuration parameter a.b', $operation->shape()->missing[0]->describe());
    }

    public function testRenderWritesEachForm(): void
    {
        self::assertSame(['SHOW ALL', 'SHOW a', 'SHOW TIME ZONE'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW ALL')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('show A')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SHOW TIME ZONE')->toString()]);
    }
}
