<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Identification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(PluginRandomPasswordIdentification::class)]
#[Medium]
final class PluginRandomPasswordIdentificationTest extends TestCase
{
    public function testKeepsThePluginNameUnresolved(): void
    {
        self::assertSame('auth_socket', (new PluginRandomPasswordIdentification('auth_socket'))->plugin);
    }

    public function testRejectsAnEmptyPluginName(): void
    {
        $this->expectException(InvalidStructure::class);
        new PluginRandomPasswordIdentification('');
    }
}
