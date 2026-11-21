<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\QualifiedTemporaryName;

#[CoversClass(QualifiedTemporaryName::class)]
#[Small]
final class QualifiedTemporaryNameTest extends TestCase
{
    public function testMessageNamesTheObjectAndTheSchema(): void
    {
        self::assertSame('Temporary object t must not be qualified with schema main.', (new QualifiedTemporaryName(new QualifiedName(new Name('t'), new Name('main'))))->message());
    }
}
