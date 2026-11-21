<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;

#[CoversClass(UnknownGrantColumn::class)]
#[Small]
final class UnknownGrantColumnTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Column c does not exist in table db.t.', (new UnknownGrantColumn(new Name('c'), new QualifiedName(new Name('t'), new Name('db'))))->message());
    }
}
