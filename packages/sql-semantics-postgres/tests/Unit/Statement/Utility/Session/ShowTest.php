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
        $operation = (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW ALL');
        self::assertSame(['name', 'setting', 'description'], [$operation->field(0)->name?->value, $operation->field(1)->name?->value, $operation->field(2)->name?->value]);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::NotNull, \SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability::Nullable], [$operation->field(0)->nullability, $operation->field(2)->nullability]);
    }

    public function testDeriveStatementNamesTheColumnOfAKeywordForm(): void
    {
        self::assertSame(['TimeZone', 'transaction_isolation', 'session_authorization'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW TIME ZONE')->field(0)->name?->value, (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW TRANSACTION ISOLATION LEVEL')->field(0)->name?->value, (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW SESSION AUTHORIZATION')->field(0)->name?->value]);
    }

    public function testDeriveStatementLeavesTheRowOfANamedParameterOpen(): void
    {
        $operation = (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW a.b');
        self::assertFalse($operation->shape()?->complete());
        self::assertSame('the session state: the registered name of the configuration parameter a.b', $operation->shape()->missing[0]->describe());
    }

    public function testRenderWritesEachForm(): void
    {
        self::assertSame(['SHOW ALL', 'SHOW a', 'SHOW TIME ZONE'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW ALL')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('show A')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('SHOW TIME ZONE')->toString()]);
    }
}
