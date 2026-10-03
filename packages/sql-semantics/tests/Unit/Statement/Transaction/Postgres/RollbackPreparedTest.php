<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier;
use SqlSemantics\Statement\Transaction\Postgres\RollbackPrepared;

#[CoversClass(RollbackPrepared::class)]
#[Small]
final class RollbackPreparedTest extends TestCase
{
    public function testToStringUsesTheDecodedGlobalIdentifier(): void
    {
        $identifier = new PreparedIdentifier('Order');
        $operation = new RollbackPrepared($identifier);
        self::assertSame($identifier, $operation->identifier);
        self::assertSame("ROLLBACK PREPARED E'Order'", $operation->toString());
    }
}
