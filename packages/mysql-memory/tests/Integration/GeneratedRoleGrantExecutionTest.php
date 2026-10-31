<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Medium]
final class GeneratedRoleGrantExecutionTest extends TestCase
{
    public function testRunCommitsBeforeRefusingTheCompleteRoleListWithoutReadingInputText(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION; INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        $request = new M\Account\Privilege\GrantRoles([new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')))], [new M\Name\CurrentUser()]);
        try {
            $this->expectExceptionCode(3524);
            (new Execution($session))->statement((new \MySqlMemory\Session\Statement\Binding())->statement($session, $request));
        } finally {
            self::assertSame('unrelated invalid input', $session->text);
            $session->query('ROLLBACK');
            $rows = $session->query('SELECT a FROM t')[0];
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
            self::assertSame([['1']], $rows->rows);
            $missing = $session->run('SHOW GRANTS FOR r')[0];
            self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
            self::assertSame(1141, $missing->getCode());
        }
    }
}
