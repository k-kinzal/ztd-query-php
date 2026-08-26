<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Mutation\MultiTableMutationRow;

#[CoversClass(MultiTableMutationRow::class)]
final class MultiTableMutationRowTest extends TestCase
{
    public function testIdentityColumnExtractsValuesAndIdentityByTargetOrdinal(): void
    {
        $codec = new MultiTableMutationRow();
        $row = [
            '__ztd_multi_1_value_0' => 41,
            '__ztd_multi_1_value_1' => 'processed',
            '__ztd_multi_1_identity_0' => 40,
        ];

        self::assertSame('__ztd_multi_1_value_2', $codec->valueColumn(1, 2));
        self::assertSame('__ztd_multi_1_identity_2', $codec->identityColumn(1, 2));
        self::assertSame(['id' => 41, 'status' => 'processed'], $codec->values($row, 1, ['id', 'status']));
        self::assertSame(['id' => 40], $codec->identity($row, 1, ['id']));
    }

    public function testValuesRejectsIncompleteProjectedRows(): void
    {
        $codec = new MultiTableMutationRow();

        self::assertNull($codec->values([], 0, ['id']));
        self::assertNull($codec->identity([], 0, ['id']));
    }
}
