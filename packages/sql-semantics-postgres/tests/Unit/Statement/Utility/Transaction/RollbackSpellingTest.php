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
        self::assertSame(['ROLLBACK', 'ABORT'], [(new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ROLLBACK WORK')->toString(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ABORT TRANSACTION')->toString()]);
    }
}
