<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Account;

use MySqlMemory\Program\Account\RevokeUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RevokeUse::class)]
#[Medium]
final class RevokeUseTest extends TestCase
{
    public function testHeaderRequiresTheDatabaseWithoutExecutingTheInstruction(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $request = new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        $this->expectExceptionCode(1046);
        (new RevokeUse())->header($request, $session);
    }

    public function testRaiseCommitsBeforeTheTargetConditionWithoutReadingInputText(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION; INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        $request = new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        try {
            $this->expectExceptionCode(1144);
            (new RevokeUse())->raise($request, $session);
        } finally {
            self::assertSame('unrelated invalid input', $session->text);
            $session->query('ROLLBACK');
            $rows = $session->query('SELECT a FROM t')[0];
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
            self::assertSame([['1']], $rows->rows);
        }
    }
}
