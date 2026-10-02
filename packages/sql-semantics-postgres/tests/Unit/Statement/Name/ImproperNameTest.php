<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ImproperName::class)]
#[Small]
final class ImproperNameTest extends TestCase
{
    public function testMessageNamesEveryPart(): void
    {
        self::assertSame('Improper qualified name (too many dotted names): a.b.', (new ImproperName(new DottedName([new Name('a'), new Name('b')])))->message());
    }
}
