<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;

#[CoversClass(DatabaseEncryption::class)]
#[Medium]
final class DatabaseEncryptionTest extends TestCase
{
    public function testRenderWritesDefaultEncryption(): void
    {
        self::assertSame("ALTER DATABASE d DEFAULT ENCRYPTION 'N'", (new Semantics(Dialect::MySql))->analyze("alter database d encryption = 'N'")->toString());
    }
}
