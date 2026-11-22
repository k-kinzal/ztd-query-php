<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AdminRows::class)]
#[Medium]
final class AdminRowsTest extends TestCase
{
    public function testAdminRecordsTheMaintenanceResult(): void
    {
        $check = (new Inspection())->inspect('CHECK TABLE t');

        self::assertSame(4, $check->fields()?->count());
        self::assertSame(Nullability::Nullable, $check->field('Msg_text')->slot->nullability);
    }

    public function testChecksumRecordsTableAndChecksum(): void
    {
        $checksum = (new Inspection())->inspect('CHECKSUM TABLE t');

        self::assertSame(Nullability::Nullable, $checksum->field('Checksum')->slot->nullability);
    }

    public function testRecoverRecordsThePreparedTransactions(): void
    {
        $recover = (new Inspection())->inspect('XA RECOVER');

        self::assertSame(Nullability::NotNull, $recover->field('data')->slot->nullability);
    }

    public function testRecordRecordsTheLayout(): void
    {
        $cache = (new Inspection())->inspect('LOAD INDEX INTO CACHE t');

        self::assertSame('Op', $cache->field(1)->slot->name?->value);
    }
}
