<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyCatalogLowering;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyCatalogLowering::class)]
#[Medium]
final class ForeignKeyCatalogLoweringTest extends TestCase
{
    public function testConstraintsProjectsTheQualifiedOwningSymbol(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a)');
        $symbols = (new ForeignKeyCatalogLowering())->constraints($operation->context, NativeAdditionFixture::owner('child'));

        self::assertCount(1, $symbols);
        self::assertSame('Prior_Fk', $symbols[0]->symbol->value);
        self::assertSame('`app`.`other`', $symbols[0]->owner->quoted());
    }

    public function testKeysProjectsTheOriginalParentPhysicalNames(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a)');
        $tables = $operation->context->tables;
        $keys = (new ForeignKeyCatalogLowering())->keys($tables[0], NativeAdditionFixture::owner('parent'));

        self::assertSame('PRIMARY', $keys[0]->identity->name->value);
        self::assertSame('a', $keys[0]->fields[0]->field->value);
    }

    public function testKeyProjectsTheStoredDescendingDirection(): void
    {
        $index = new IndexDeclaration(
            new Name('by_x'),
            PhysicalIndexKind::Index,
            true,
            leadingFields: [new Name('x')],
            leadingDescending: [true]
        );
        $key = (new ForeignKeyCatalogLowering())->key($index, NativeAdditionFixture::owner('child'));

        self::assertSame('by_x', $key->identity->name->value);
        self::assertTrue($key->fields[0]->descending);
    }
}
