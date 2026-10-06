<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys;

#[CoversClass(ToggleKeys::class)]
#[Medium]
final class ToggleKeysTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t ENABLE KEYS', [$table])->facts->diagnostics);
    }

    public function testRenderWritesEnableOrDisable(): void
    {
        self::assertSame('ALTER TABLE t ENABLE KEYS, DISABLE KEYS', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ENABLE KEYS, DISABLE KEYS')->toString());
    }
}
