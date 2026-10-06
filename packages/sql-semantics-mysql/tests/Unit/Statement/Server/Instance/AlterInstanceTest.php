<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance;

#[CoversClass(AlterInstance::class)]
#[Medium]
final class AlterInstanceTest extends TestCase
{
    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_main', (new Semantics(Dialect::MySql))->analyze('alter instance reload tls for channel mysql_main')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('ALTER INSTANCE RELOAD KEYRING');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
