<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\MySql\Commit;

#[CoversClass(Commit::class)]
#[Small]
final class CommitTest extends TestCase
{
    #[TestWith([true, true, true])]
    #[TestWith([true, false, false])]
    #[TestWith([false, true, false])]
    #[TestWith([null, null, false])]
    public function testConflictsIdentifiesContradictoryConnectionRequirements(?bool $chain, ?bool $release, bool $expected): void
    {
        self::assertSame($expected, (new Commit($chain, $release))->conflicts());
    }

    #[TestWith([null, null, 'COMMIT'])]
    #[TestWith([true, null, 'COMMIT AND CHAIN'])]
    #[TestWith([false, false, 'COMMIT AND NO CHAIN NO RELEASE'])]
    #[TestWith([null, true, 'COMMIT RELEASE'])]
    #[TestWith([true, true, 'COMMIT AND CHAIN RELEASE'])]
    public function testToStringPreservesExplicitOverrides(?bool $chain, ?bool $release, string $expected): void
    {
        self::assertSame($expected, (new Commit($chain, $release))->toString());
    }
}
