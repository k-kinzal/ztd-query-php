<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupState;
use SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResourceGroupState::class)]
#[Medium]
final class ResourceGroupStateTest extends TestCase
{
    public function testAGroupIsCreatedEnabledUnlessDisabled(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $enabled = $binder->bind('CREATE RESOURCE GROUP g TYPE = USER');
        $disabled = $binder->bind('CREATE RESOURCE GROUP g TYPE = USER DISABLE');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $enabled);
        self::assertInstanceOf(CreateResourceGroupStatement::class, $disabled);
        self::assertSame(ResourceGroupState::Enabled, $enabled->state);
        self::assertSame(ResourceGroupState::Disabled, $disabled->state);
    }
}
