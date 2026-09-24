<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Session\SessionReports;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticCountStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticsStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowGrantsStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowStatusStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowVariablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SessionReports::class)]
#[Medium]
final class SessionReportsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindRoutesDiagnosticsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $warnings = $binder->bind('SHOW WARNINGS');
        $count = $binder->bind('SHOW COUNT(*) ERRORS');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $warnings);
        self::assertInstanceOf(ShowDiagnosticCountStatement::class, $count);
        self::assertSame([DiagnosticSelection::Warnings, DiagnosticSelection::Errors], [$warnings->selection, $count->selection]);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testVariablesReadsScopeAndConditionAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $variables = $binder->bind("SHOW GLOBAL VARIABLES WHERE Value <> ''");
        $status = $binder->bind('SHOW LOCAL STATUS');
        self::assertInstanceOf(ShowVariablesStatement::class, $variables);
        self::assertInstanceOf(ShowStatusStatement::class, $status);
        self::assertInstanceOf(ConditionFilter::class, $variables->filter);
        self::assertSame([VariableScope::Global, VariableScope::Session], [$variables->scope, $status->scope]);
        self::assertSame("SHOW GLOBAL VARIABLES WHERE (`Value` <> '')", $variables->toString());
    }

    public function testGrantsReadsTheAccountAndRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW GRANTS FOR u@'h' USING r, CURRENT_USER");
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        self::assertEquals(new AccountName('u', 'h'), $statement->account);
        self::assertEquals([new AccountName('r'), CurrentAccount::Authenticated], $statement->roles);
    }

    #[TestWith(['SHOW GRANTS FOR CURRENT_USER'])]
    #[TestWith(['SHOW GRANTS FOR `CURRENT_USER`'])]
    public function testAccountDistinguishesTheKeywordFromAQuotedName(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(ShowGrantsStatement::class, $statement);
        self::assertSame(str_contains($sql, '`'), $statement->account instanceof AccountName);
    }
}
