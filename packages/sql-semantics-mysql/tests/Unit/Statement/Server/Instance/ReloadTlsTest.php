<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Instance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ReloadTls::class)]
#[Medium]
final class ReloadTlsTest extends TestCase
{
    public function testRenderWritesNoRollbackOnError(): void
    {
        self::assertSame('ALTER INSTANCE RELOAD TLS NO ROLLBACK ON ERROR', (new Inspection())->inspect('alter instance reload tls no rollback on error')->toString());
    }
}
