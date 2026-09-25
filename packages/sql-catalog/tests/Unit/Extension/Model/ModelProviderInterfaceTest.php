<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\Model\ModelProviderInterface;

#[CoversClass(ModelProviderInterface::class)]
#[UsesClass(\SqlCatalog\Extension\ExtensionRegistry::class)]
final class ModelProviderInterfaceTest extends TestCase
{
    public function testModelsCanBeProvidedOutsideTheBuiltins(): void
    {
        $extension = self::createStub(ModelProviderInterface::class);
        $extension->method('name')->willReturn('custom');
        $registry = new \SqlCatalog\Extension\ExtensionRegistry([$extension]);
        self::assertSame([$extension], $registry->modelProvidersOf(['custom']));
        self::assertSame([], $registry->modelProvidersOf([]));
    }
}
