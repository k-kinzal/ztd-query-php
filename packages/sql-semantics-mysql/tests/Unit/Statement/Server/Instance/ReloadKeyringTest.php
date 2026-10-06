<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadKeyring;

#[CoversClass(ReloadKeyring::class)]
#[Medium]
final class ReloadKeyringTest extends TestCase
{
    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER INSTANCE RELOAD KEYRING', (new Semantics(Dialect::MySql))->analyze('alter instance reload keyring')->toString());
    }
}
