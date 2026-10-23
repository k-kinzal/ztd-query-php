<?php

declare(strict_types=1);

namespace Tests\Unit\Program\Account;

use MySqlMemory\Program\Account\RenameUse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(RenameUse::class)]
#[Medium]
final class RenameUseTest extends TestCase
{
    public function testRunCommitsBeforeRefusingTheCompleteRenameListWithoutReadingInputText(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); START TRANSACTION; INSERT INTO t VALUES(1)');
        $session->text = 'unrelated invalid input';
        $request = new M\Routine\Program\Write\DeferredRenameUser([new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\CurrentUser())]);
        try {
            $this->expectExceptionCode(1396);
            (new RenameUse())->run($request, $session);
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
