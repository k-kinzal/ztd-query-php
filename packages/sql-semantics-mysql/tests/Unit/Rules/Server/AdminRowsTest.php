<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\AdminRows;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(AdminRows::class)]
#[Medium]
final class AdminRowsTest extends TestCase
{
    public function testAdminRecordsTheMaintenanceResult(): void
    {
        $check = (new Semantics(Dialect::MySql))->analyze('CHECK TABLE t');

        self::assertSame(4, $check->fields()?->count());
        self::assertSame(Nullability::Nullable, $check->field('Msg_text')->slot->nullability);
    }

    public function testChecksumRecordsTableAndChecksum(): void
    {
        $checksum = (new Semantics(Dialect::MySql))->analyze('CHECKSUM TABLE t');

        self::assertSame(Nullability::Nullable, $checksum->field('Checksum')->slot->nullability);
    }

    public function testRecoverRecordsThePreparedTransactions(): void
    {
        $recover = (new Semantics(Dialect::MySql))->analyze('XA RECOVER');

        self::assertSame(Nullability::NotNull, $recover->field('data')->slot->nullability);
    }

    public function testRecordRecordsTheLayout(): void
    {
        $cache = (new Semantics(Dialect::MySql))->analyze('LOAD INDEX INTO CACHE t');

        self::assertSame('Op', $cache->field(1)->slot->name?->value);
    }
}
