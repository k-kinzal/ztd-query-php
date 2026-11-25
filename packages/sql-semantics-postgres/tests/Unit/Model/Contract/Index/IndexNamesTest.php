<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNames;

#[CoversClass(IndexNames::class)]
#[Small]
final class IndexNamesTest extends TestCase
{
    public function testAttributesAllocatesTheFirstFreeLabelInOccurrenceOrder(): void
    {
        $id = 'id';
        $occupied = 'id1';

        self::assertSame(['id', 'id1', 'id11', 'id2'], IndexNames::attributes([$id, $id, $occupied, $id]));
    }

    public function testClipPreservesCompleteUtf8Characters(): void
    {
        self::assertSame('勘', IndexNames::clip('勘定', 4));
    }

    public function testCandidateClipsBothUtf8PartsAndRetainsTheSuffix(): void
    {
        $table = str_repeat('表', 20);
        $field = str_repeat('列', 20);

        self::assertSame(
            str_repeat('表', 9) . '_' . str_repeat('列', 9) . '_idx11',
            IndexNames::candidate($table, $field, 'idx11'),
        );
    }
}
