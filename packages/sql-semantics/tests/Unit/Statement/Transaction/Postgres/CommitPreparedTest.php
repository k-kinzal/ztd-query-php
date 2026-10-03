<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\CommitPrepared;
use SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier;

#[CoversClass(CommitPrepared::class)]
#[Small]
final class CommitPreparedTest extends TestCase
{
    public function testToStringUsesTheDecodedGlobalIdentifier(): void
    {
        $identifier = new PreparedIdentifier('Order');
        $operation = new CommitPrepared($identifier);
        self::assertSame($identifier, $operation->identifier);
        self::assertSame("COMMIT PREPARED E'Order'", $operation->toString());
    }
}
