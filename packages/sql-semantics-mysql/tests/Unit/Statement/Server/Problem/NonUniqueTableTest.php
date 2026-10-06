<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(NonUniqueTable::class)]
#[Small]
final class NonUniqueTableTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame("Not unique table/alias: 'a'.", (new NonUniqueTable(new Name('a')))->message());
    }
}
