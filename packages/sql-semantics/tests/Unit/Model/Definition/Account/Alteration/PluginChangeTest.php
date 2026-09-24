<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Alteration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(PluginChange::class)]
#[Medium]
final class PluginChangeTest extends TestCase
{
    public function testKeepsTheAccountAndPlugin(): void
    {
        $change = new PluginChange(new AccountName('u', 'h'), 'auth_socket');
        self::assertEquals(new AccountName('u', 'h'), $change->account);
        self::assertSame('auth_socket', $change->plugin);
    }

    public function testRejectsAnEmptyPluginName(): void
    {
        $this->expectException(InvalidStructure::class);
        new PluginChange(new AccountName('u'), '');
    }
}
