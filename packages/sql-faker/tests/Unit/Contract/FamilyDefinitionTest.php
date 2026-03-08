<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\FamilyDefinition;

#[CoversClass(FamilyDefinition::class)]
final class FamilyDefinitionTest extends TestCase
{
    public function testConstructsReadonlyFamilyDefinition(): void
    {
        $definition = new FamilyDefinition(
            'family.id',
            'family description',
            'contract',
            ['stmt', 'expr'],
            ['arity'],
            ['row_arity'],
        );

        self::assertSame('family.id', $definition->id);
        self::assertSame('family description', $definition->description);
        self::assertSame('contract', $definition->layer);
        self::assertSame(['stmt', 'expr'], $definition->anchorRules);
        self::assertSame(['arity'], $definition->parameterNames);
        self::assertSame(['row_arity'], $definition->propertyNames);
    }
}
