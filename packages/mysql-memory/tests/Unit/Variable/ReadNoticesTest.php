<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Variable\ReadNotices;
use MySqlMemory\Variable\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReadNotices::class)]
#[Small]
final class ReadNoticesTest extends TestCase
{
    public function testReadRecordsOnlyTheDeprecatedSessionScope(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $notices = new ReadNotices();
        $notices->read('gtid_executed', Scope::Global, $context);
        $notices->read('gtid_mode', Scope::Session, $context);
        self::assertSame([], $session->diagnostics->conditions);
        $notices->read('gtid_executed', Scope::Session, $context);
        self::assertSame([['Warning', 1681, "'@@SESSION.GTID_EXECUTED' is deprecated and will be removed in a future release."]], $session->diagnostics->conditions);
    }
}
