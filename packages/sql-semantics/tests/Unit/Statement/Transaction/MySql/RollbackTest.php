<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\MySql\Rollback;

#[CoversClass(Rollback::class)]
#[Small]
final class RollbackTest extends TestCase
{
    #[TestWith([true, true, true])]
    #[TestWith([true, false, false])]
    #[TestWith([false, true, false])]
    #[TestWith([null, null, false])]
    public function testConflictsIdentifiesContradictoryConnectionRequirements(?bool $chain, ?bool $release, bool $expected): void
    {
        self::assertSame($expected, (new Rollback($chain, $release))->conflicts());
    }

    #[TestWith([null, null, 'ROLLBACK'])]
    #[TestWith([true, null, 'ROLLBACK AND CHAIN'])]
    #[TestWith([false, false, 'ROLLBACK AND NO CHAIN NO RELEASE'])]
    #[TestWith([null, true, 'ROLLBACK RELEASE'])]
    #[TestWith([true, true, 'ROLLBACK AND CHAIN RELEASE'])]
    public function testToStringPreservesExplicitOverrides(?bool $chain, ?bool $release, string $expected): void
    {
        self::assertSame($expected, (new Rollback($chain, $release))->toString());
    }
}
