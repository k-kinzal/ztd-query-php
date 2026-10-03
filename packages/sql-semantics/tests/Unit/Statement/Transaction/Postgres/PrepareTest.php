<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\Postgres;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\Postgres\Prepare;
use SqlSemantics\Statement\Transaction\Postgres\PreparedIdentifier;

#[CoversClass(Prepare::class)]
#[Small]
final class PrepareTest extends TestCase
{
    public function testToStringUsesTheDecodedGlobalIdentifier(): void
    {
        $identifier = new PreparedIdentifier('Order');
        $operation = new Prepare($identifier);
        self::assertSame($identifier, $operation->identifier);
        self::assertSame("PREPARE TRANSACTION E'Order'", $operation->toString());
    }
}
