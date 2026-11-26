<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ToggleKeys;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ToggleKeys::class)]
#[Medium]
final class ToggleKeysTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->inspect('ALTER TABLE t ENABLE KEYS', [$table])->facts->diagnostics);
    }

    public function testRenderWritesEnableOrDisable(): void
    {
        self::assertSame('ALTER TABLE t ENABLE KEYS, DISABLE KEYS', (new Inspection())->inspect('ALTER TABLE t ENABLE KEYS, DISABLE KEYS')->toString());
    }
}
