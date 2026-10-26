<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Statement;

use MySqlMemory\Instance;
use MySqlMemory\Session\Statement\TransactionNotices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionNotices::class)]
#[Small]
final class TransactionNoticesTest extends TestCase
{
    public function testReadUsesTheConcreteOperandWithoutRequestText(): void
    {
        $session = (new Instance('8.4.7'))->connect();
        $session->text = 'not SQL';
        $statement = new \SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint(new \SqlSemantics\Statement\Identifier\Name('FULL', \SqlSemantics\Statement\Identifier\NameQuote::Bare));
        (new TransactionNotices())->read($statement, $session);
        self::assertSame([['Warning', 4119, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull->value]], $session->diagnostics->conditions);
    }

}
