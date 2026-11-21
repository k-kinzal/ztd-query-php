<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\RollbackSpelling::class)]
#[Medium]
final class RollbackSpellingTest extends TestCase
{
    public function testBothSpellingsAreKept(): void
    {
        self::assertSame(['ROLLBACK', 'ABORT'], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('ROLLBACK WORK')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('ABORT TRANSACTION')->toString()]);
    }
}
