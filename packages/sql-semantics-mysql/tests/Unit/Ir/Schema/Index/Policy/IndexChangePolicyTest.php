<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexLock;

#[CoversClass(IndexChangePolicy::class)]
#[Small]
final class IndexChangePolicyTest extends TestCase
{
    public function testSupportsChecksOwnerLifetimeAndRemovalRole(): void
    {
        $copy = new IndexChangePolicy(IndexAlgorithm::Copy, IndexLock::None);
        $inPlace = new IndexChangePolicy(IndexAlgorithm::InPlace, IndexLock::Shared);
        $native = new IndexChangePolicy();

        self::assertTrue($copy->supports(ExternalRelationKind::TemporaryTable, true));
        self::assertFalse($copy->supports(ExternalRelationKind::BaseTable));
        self::assertTrue($inPlace->supports(ExternalRelationKind::BaseTable));
        self::assertFalse($inPlace->supports(ExternalRelationKind::BaseTable, true));
        self::assertFalse($inPlace->supports(ExternalRelationKind::TemporaryTable));
        self::assertTrue($native->supports(ExternalRelationKind::BaseTable, true));
    }

    public function testSqlWritesOnlyNondefaultOptions(): void
    {
        self::assertSame('', (new IndexChangePolicy())->sql());
        self::assertSame(' ALGORITHM=INPLACE LOCK=NONE', (new IndexChangePolicy(IndexAlgorithm::InPlace, IndexLock::None))->sql());
        self::assertSame(' LOCK=EXCLUSIVE', (new IndexChangePolicy(lock: IndexLock::Exclusive))->sql());
    }
}
