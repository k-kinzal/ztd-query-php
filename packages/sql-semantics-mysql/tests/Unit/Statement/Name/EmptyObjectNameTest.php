<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Name\EmptyObjectName;
use SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind;

#[CoversClass(EmptyObjectName::class)]
#[Small]
final class EmptyObjectNameTest extends TestCase
{
    public function testMessageNamesTheInvalidRole(): void
    {
        self::assertSame("Incorrect table name ''", (new EmptyObjectName(ObjectNameKind::Table))->message());
        self::assertSame("Incorrect server name value: ''", (new EmptyObjectName(ObjectNameKind::Server))->message());
        self::assertSame("Incorrect database name ''", (new EmptyObjectName(ObjectNameKind::Database))->message());
    }
}
