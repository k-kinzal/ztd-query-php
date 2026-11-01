<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Entry\Account;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\Entry\Account\Recipients;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;

#[CoversClass(Recipients::class)]
#[Medium]
final class RecipientsTest extends TestCase
{
    public function testNotesWarnOfEveryUnresolvableHostBeforeTheLevelRefusal(): void
    {
        $session = (new Instance())->connect();
        $sql = "GRANT SUPER ON d.t TO u@host, v@'1.2.3.4', w@other";
        try {
            $session->semantics()->analyze($sql);
            self::fail('SUPER is a global privilege.');
        } catch (RejectedInput $failure) {
            $notes = (new Recipients())->notes($failure, $session->semantics()->parser()->parse($sql), $session);
        }
        self::assertSame([28, 50], array_column($notes, 0));
        self::assertSame([1285, 1285], array_map(static fn (array $note): int => $note[1]->code->number(), $notes));
    }

    public function testNotesLeaveOtherRefusalsAlone(): void
    {
        $session = (new Instance())->connect();
        $sql = 'SELECT 1 FROM t, t';
        try {
            $session->semantics()->analyze($sql);
            self::fail('The relation is named twice.');
        } catch (RejectedInput $failure) {
            self::assertSame([], (new Recipients())->notes($failure, $session->semantics()->parser()->parse($sql), $session));
        }
    }
}
