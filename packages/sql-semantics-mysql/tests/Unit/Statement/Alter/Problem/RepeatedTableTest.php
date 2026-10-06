<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RepeatedTable::class)]
#[Small]
final class RepeatedTableTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Table db.t is named more than once.', (new RepeatedTable(new QualifiedName(new Name('t'), new Name('db'))))->message());
    }
}
