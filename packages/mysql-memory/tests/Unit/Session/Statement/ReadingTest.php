<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Session\Statement\Reading;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\NameQuote;

#[CoversClass(Reading::class)]
#[Small]
final class ReadingTest extends TestCase
{
    public function testReadKeepsAccountOperandNoticesOutOfReplay(): void
    {
        $statement = new RenameUser([new UserRenaming(new AccountName(new Name('full', NameQuote::Bare)), new CurrentUser())]);
        $session = (new Instance())->connect();
        $reading = new Reading();
        $reading->read($statement, $session, false);
        self::assertSame([], $session->diagnostics->conditions);
        $reading->read($statement, $session);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
    }

    public function testReadKeepsTransactionOperandNoticesOutOfReplay(): void
    {
        $statement = new Savepoint(new Name('full', NameQuote::Bare));
        $session = (new Instance())->connect();
        $reading = new Reading();
        $reading->read($statement, $session, false);
        self::assertSame([], $session->diagnostics->conditions);
        $reading->read($statement, $session);
        self::assertSame([4119], array_column($session->diagnostics->conditions, 1));
    }
}
