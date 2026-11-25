<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexAutoIncrementLowering;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;

#[CoversClass(IndexAutoIncrementLowering::class)]
#[Small]
final class IndexAutoIncrementLoweringTest extends TestCase
{
    public function testResolveSelectsOnlyDistinctBackingKeysOfTheGeneratingField(): void
    {
        $field = new Name('id');
        $primary = new IndexDeclaration(new Name('PRIMARY'), PhysicalIndexKind::Primary, true, autoIncrementField: $field);
        $unrelated = new IndexDeclaration(new Name('unrelated'), PhysicalIndexKind::Index, true);
        $alternate = new IndexDeclaration(new Name('retained'), PhysicalIndexKind::Index, true, autoIncrementField: $field);
        $lowering = new IndexAutoIncrementLowering();
        $keys = [$primary, $unrelated, $alternate];
        $support = $lowering->resolve($primary, $keys);

        self::assertSame('id', $support?->field->value);
        self::assertSame('retained', $support->alternates[0]->value);
        self::assertNull($lowering->resolve($unrelated, $keys));
    }
}
