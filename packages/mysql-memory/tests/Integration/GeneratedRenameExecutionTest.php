<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversNothing]
#[Medium]
final class GeneratedRenameExecutionTest extends TestCase
{
    public function testSqlRenamePreservesPrivilegesAcrossAnOrderedChain(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE USER a');
        $session->query('GRANT SELECT ON d.* TO a');
        $session->query('RENAME USER a TO b, b TO c');
        $grants = $session->query('SHOW GRANTS FOR c')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $grants);
        self::assertSame([['GRANT USAGE ON *.* TO `c`@`%`'], ['GRANT SELECT ON `d`.* TO `c`@`%`']], $grants->rows);
    }

    public function testSqlRenameRefusesARoleBeforeReadingSourceAccounts(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query('CREATE ROLE r');
        $session->query('CREATE USER u DEFAULT ROLE r');
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(3532);
        $this->expectExceptionMessage('Renaming of a role identifier is forbidden');
        $session->query('RENAME USER nobody TO r');
    }

    public function testRunCommitsBeforeRefusingTheCompleteRenameListWithoutReadingInputText(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION; INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        $request = new M\Account\RenameUser([new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\CurrentUser())]);
        try {
            $this->expectExceptionCode(1396);
            (new Execution($session))->statement($request);
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
