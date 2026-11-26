<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadKeyring;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ReloadKeyring::class)]
#[Medium]
final class ReloadKeyringTest extends TestCase
{
    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER INSTANCE RELOAD KEYRING', (new Inspection())->inspect('alter instance reload keyring')->toString());
    }
}
