<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\Chaining::class)]
#[Medium]
final class ChainingTest extends TestCase
{
    public function testBothClausesAreKept(): void
    {
        self::assertSame(['COMMIT AND CHAIN', 'COMMIT AND NO CHAIN'], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('COMMIT AND CHAIN')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('COMMIT AND NO CHAIN')->toString()]);
    }
}
