<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\BeginSpelling::class)]
#[Medium]
final class BeginSpellingTest extends TestCase
{
    public function testBothSpellingsAreKept(): void
    {
        self::assertSame(['BEGIN', 'START TRANSACTION'], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('BEGIN')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('START TRANSACTION')->toString()]);
    }
}
