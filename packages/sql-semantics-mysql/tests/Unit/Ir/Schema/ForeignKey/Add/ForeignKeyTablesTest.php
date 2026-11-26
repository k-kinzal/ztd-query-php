<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyTables;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Testing\MySql\NativeAdditionFixture;

#[CoversClass(ForeignKeyTables::class)]
#[Medium]
final class ForeignKeyTablesTest extends TestCase
{
    public function testChildSelectsTheBoundAlterTable(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a)');
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        [$table, $owner] = (new ForeignKeyTables())->child($operation, $operation->statement);

        self::assertSame('child', $table->name->name->value);
        self::assertSame('`app`.`child`', $owner->quoted());
    }

    public function testParentKeepsThePersistentTableDespiteATemporaryShadow(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY (x) REFERENCES parent(a)');
        self::assertInstanceOf(AlterTable::class, $operation->statement);
        $command = $operation->statement->commands[0];
        self::assertInstanceOf(AddConstraint::class, $command);
        self::assertInstanceOf(ForeignKey::class, $command->element);
        [$parent, $owner] = (new ForeignKeyTables())->parent($operation, $command->element->references);

        self::assertFalse($parent->temporary);
        self::assertSame('`app`.`parent`', $owner->quoted());
    }

    public function testIdentityPreservesTheResolvedPersistentDatabase(): void
    {
        $operation = NativeAdditionFixture::operation('ALTER TABLE app.child ADD FOREIGN KEY(x) REFERENCES parent(a)');
        $identity = (new ForeignKeyTables())->identity($operation->context->tables[0], $operation->context);

        self::assertSame('`app`.`parent`', $identity->quoted());
    }
}
