<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\Session\SettingDisplays;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\Show\ShowAllSettingsStatement;
use SqlSemantics\Model\Statement\Configuration\Show\ShowSettingStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SettingDisplays::class)]
#[Medium]
final class SettingDisplaysTest extends TestCase
{
    #[TestWith(['SHOW work_mem', ShowSettingStatement::class, 'SHOW "work_mem"'])]
    #[TestWith(['SHOW a."B".c', ShowSettingStatement::class, 'SHOW "a"."B"."c"'])]
    #[TestWith(['SHOW TIME ZONE', ShowSettingStatement::class, 'SHOW "timezone"'])]
    #[TestWith(['SHOW TRANSACTION ISOLATION LEVEL', ShowSettingStatement::class, 'SHOW "transaction_isolation"'])]
    #[TestWith(['SHOW SESSION AUTHORIZATION', ShowSettingStatement::class, 'SHOW "session_authorization"'])]
    #[TestWith(['SHOW ALL', ShowAllSettingsStatement::class, 'SHOW ALL'])]
    #[TestWith(['SHOW "ALL"', ShowAllSettingsStatement::class, 'SHOW ALL'])]
    public function testBindNamesTheDisplayedParameter(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
