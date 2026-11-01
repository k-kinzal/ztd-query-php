<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Command\Prepared\PreparedStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PreparedStatement::class)]
#[Medium]
final class PreparedRevocationBoundaryTest extends TestCase
{
    public function testParametersHasNoValueMarkers(): void
    {
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $request);
        self::assertSame([], \MySqlMemory\Command\Prepared\ParameterBindings::capture($operation)->types);
    }

    public function testExecuteUsesTheOriginalDatabaseAndRestoresTheCurrentDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new \MySqlMemory\Session\Access\Preparation())->read('REVOKE ALL ON PROCEDURE * FROM u', $session);
        self::assertInstanceOf(M\Statement::class, $operation);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        $definition = PreparedStatement::capture($operation, $session);
        $session->query('DROP DATABASE d');
        $this->expectExceptionCode(1144);
        try {
            $definition->execute($session, 'opaque request label', []);
        } finally {
            self::assertSame('', $session->variables->database);
            self::assertSame('opaque request label', $session->text);
        }
    }

    public function testRunPreservesTheSameInstructionAndItsDiagnostics(): void
    {
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $operation = (new \MySqlMemory\Session\Statement\Binding())->operation($session, $request);
        $definition = PreparedStatement::capture($operation, $session);
        $reply = $definition->run($session, 'opaque label', []);
        self::assertSame($operation, $definition->statement);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1144, $reply[0]->getCode());
        self::assertSame([['Error', 1144, 'Illegal GRANT/REVOKE command; please consult the manual to see which privileges can be used']], $session->diagnostics->conditions);
    }
    public function testRefusedRetainsThePreparationAndInvocationBoundary(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query("PREPARE st FROM 'REVOKE ALL ON PROCEDURE * FROM u'");
        self::assertSame([], $session->diagnostics->conditions);
        $reply = $session->run('EXECUTE st');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1144, $reply[0]->getCode());
        self::assertSame([['Error', 1144, 'Illegal GRANT/REVOKE command; please consult the manual to see which privileges can be used']], $session->diagnostics->conditions);
    }

    public function testRefusedRequiresTheCurrentDatabaseBeforePreparingAWildcard(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $reply = $session->run("PREPARE st FROM 'REVOKE ALL ON PROCEDURE * FROM u'");
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1046, $reply[0]->getCode());
        $missing = $session->run('EXECUTE st');
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing[0]);
        self::assertSame(1243, $missing[0]->getCode());
    }
    public function testHeaderRequiresTheDatabaseWithoutExecutingTheInstruction(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        $this->expectExceptionCode(1046);
        (new \MySqlMemory\Command\Account\RevokeCommand())->prepare($request, $session);
    }

    public function testRaiseCommitsBeforeTheTargetConditionWithoutReadingInputText(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION; INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        try {
            $this->expectExceptionCode(1144);
            (new \MySqlMemory\Session\Execution($session))->statement((new \MySqlMemory\Session\Statement\Binding())->operation($session, $request));
        } finally {
            self::assertSame('unrelated invalid input', $session->text);
            $session->query('ROLLBACK');
            $rows = $session->query('SELECT a FROM t')[0];
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
            self::assertSame([['1']], $rows->rows);
        }
    }
}
