<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;

#[CoversClass(UnknownDeleteTable::class)]
#[Small]
final class UnknownDeleteTableTest extends TestCase
{
    public function testMessageNamesTheTable(): void
    {
        self::assertSame("Unknown table 'u' in MULTI DELETE", (new UnknownDeleteTable(new QualifiedName(new Name('u'))))->message());
    }
}
