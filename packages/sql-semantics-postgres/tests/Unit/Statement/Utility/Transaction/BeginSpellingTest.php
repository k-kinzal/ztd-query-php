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
        self::assertSame(['BEGIN', 'START TRANSACTION'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('BEGIN')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('START TRANSACTION')->toString()]);
    }
}
