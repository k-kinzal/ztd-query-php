<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\SelectOptionConflict;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

#[CoversClass(SelectOptionConflict::class)]
#[Small]
final class SelectOptionConflictTest extends TestCase
{
    public function testRepeatedTellsTheSameModifierFromOppositeOnes(): void
    {
        self::assertTrue((new SelectOptionConflict(SelectOption::Cache, SelectOption::Cache))->repeated());
        self::assertFalse((new SelectOptionConflict(SelectOption::Cache, SelectOption::NoCache))->repeated());
    }

    public function testMessageNamesTheModifiersInWrittenOrder(): void
    {
        self::assertSame("Option 'SQL_NO_CACHE' used twice in statement", (new SelectOptionConflict(SelectOption::NoCache, SelectOption::NoCache))->message());
        self::assertSame('Incorrect usage of SQL_NO_CACHE and SQL_CACHE', (new SelectOptionConflict(SelectOption::NoCache, SelectOption::Cache))->message());
    }
}
