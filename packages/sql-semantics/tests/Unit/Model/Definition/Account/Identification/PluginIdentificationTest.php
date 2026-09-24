<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Identification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Identification\PluginIdentification;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(PluginIdentification::class)]
#[Medium]
final class PluginIdentificationTest extends TestCase
{
    public function testKeepsThePluginNameUnresolved(): void
    {
        self::assertSame('auth_socket', (new PluginIdentification('auth_socket'))->plugin);
    }

    public function testRejectsAnEmptyPluginName(): void
    {
        $this->expectException(InvalidStructure::class);
        new PluginIdentification('');
    }
}
