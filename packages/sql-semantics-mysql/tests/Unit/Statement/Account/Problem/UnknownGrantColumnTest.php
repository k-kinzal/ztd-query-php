<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(UnknownGrantColumn::class)]
#[Small]
final class UnknownGrantColumnTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Column c does not exist in table db.t.', (new UnknownGrantColumn(new Name('c'), new QualifiedName(new Name('t'), new Name('db'))))->message());
    }
}
