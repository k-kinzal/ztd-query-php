<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;

#[CoversClass(NullablePrimaryKey::class)]
#[Small]
final class NullablePrimaryKeyTest extends TestCase
{
    public function testMessageNamesTheColumn(): void
    {
        self::assertSame('All parts of a PRIMARY KEY must be NOT NULL; column a is declared NULL.', (new NullablePrimaryKey(new Name('a')))->message());
    }
}
