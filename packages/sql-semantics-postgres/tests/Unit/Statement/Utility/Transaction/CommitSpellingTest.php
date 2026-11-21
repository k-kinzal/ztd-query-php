<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\CommitSpelling::class)]
#[Medium]
final class CommitSpellingTest extends TestCase
{
    public function testBothSpellingsAreKept(): void
    {
        self::assertSame(['COMMIT', 'END'], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('COMMIT WORK')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->inspect('END TRANSACTION')->toString()]);
    }
}
