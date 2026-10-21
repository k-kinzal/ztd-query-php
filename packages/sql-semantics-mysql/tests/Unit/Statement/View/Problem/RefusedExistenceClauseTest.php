<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedExistenceClause;

#[CoversClass(RefusedExistenceClause::class)]
#[Small]
final class RefusedExistenceClauseTest extends TestCase
{
    public function testMessageDescribesTheIncompatibleClauseWithoutKeepingSource(): void
    {
        $problem = new RefusedExistenceClause(11);
        self::assertSame('IF NOT EXISTS requires CREATE VIEW without OR REPLACE.', $problem->message());
        self::assertSame(['offset' => 11], get_object_vars($problem));
    }

    public function testValueRejectsANegativePosition(): void
    {
        $this->expectExceptionMessage('A refused view clause begins at a nonnegative byte offset.');
        new RefusedExistenceClause(-1);
    }
}
