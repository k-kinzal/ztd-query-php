<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\Commit;

#[CoversClass(Commit::class)]
#[Small]
final class CommitTest extends TestCase
{
    #[TestWith([false, 'COMMIT'])]
    #[TestWith([true, 'COMMIT AND CHAIN'])]
    public function testToStringPreservesTheChainingRequest(bool $chain, string $expected): void
    {
        self::assertSame($expected, (new Commit($chain))->toString());
    }
}
