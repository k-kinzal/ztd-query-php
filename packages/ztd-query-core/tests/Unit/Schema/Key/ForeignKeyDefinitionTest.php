<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\ReferentialAction;

#[CoversClass(ForeignKeyDefinition::class)]
final class ForeignKeyDefinitionTest extends TestCase
{
    public function testCarriesCompositeReferenceAndActions(): void
    {
        $definition = new ForeignKeyDefinition(
            ['tenant_id', 'department_id'],
            'departments',
            ['tenant_id', 'id'],
            ReferentialAction::Cascade,
            ReferentialAction::SetNull,
        );

        self::assertSame(['tenant_id', 'department_id'], $definition->columns);
        self::assertSame('departments', $definition->referencedTable);
        self::assertSame(['tenant_id', 'id'], $definition->referencedColumns);
        self::assertSame(ReferentialAction::Cascade, $definition->onDelete);
        self::assertSame(ReferentialAction::SetNull, $definition->onUpdate);
    }
}
