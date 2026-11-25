<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ForeignKeyRemovalLowering::class)]
#[Medium]
final class ForeignKeyRemovalLoweringTest extends TestCase
{
    public function testChangesRecognizesOnlyNativeConstraintAndIndexRemoval(): void
    {
        $statement = (new Inspection())->inspect('ALTER TABLE app.c DROP INDEX by_x, DROP FOREIGN KEY named_fk')->statement;
        self::assertInstanceOf(AlterTable::class, $statement);
        $actions = (new ForeignKeyRemovalLowering())->changes($statement);

        self::assertNotNull($actions);
        self::assertSame([ElementKind::Index, ElementKind::ForeignKey], array_column($actions, 'kind'));
    }

    public function testLowerAllowsTheSameStatementToRemoveTheLastSupport(): void
    {
        $s = new Semantics();
        $catalog = $s->context([
            'CREATE TABLE app.p (a INT PRIMARY KEY)',
            'CREATE TABLE app.c (x INT, CONSTRAINT Physical_Key FOREIGN KEY (x) REFERENCES p(a))',
        ]);
        $result = $s->analyze('ALTER TABLE app.c DROP INDEX physical_key, DROP FOREIGN KEY physical_key', $catalog);

        self::assertInstanceOf(DropForeignKeys::class, $result);
        self::assertSame('ALTER TABLE `app`.`c` DROP INDEX `Physical_Key`, DROP FOREIGN KEY `Physical_Key`', $result->toString());
    }

    public function testOwnerUsesTheDeclaredTemporaryTable(): void
    {
        $catalog = (new Semantics())->context([
            'CREATE TABLE app.c (p INT)',
            'CREATE TEMPORARY TABLE app.c (q INT)',
        ]);
        $inspected = (new Inspection())->inspect('ALTER TABLE app.c DROP FOREIGN KEY supplied_key', $catalog);
        self::assertInstanceOf(AlterTable::class, $inspected->statement);
        $owner = (new ForeignKeyRemovalLowering())->owner($inspected->binding(), $inspected->statement);

        self::assertSame(ExternalRelationKind::TemporaryTable, $owner->kind);
        self::assertSame('app', $owner->name->schema->value);
    }

    public function testConstraintResolvesAStableGeneratedName(): void
    {
        $s = new Semantics();
        $catalog = $s->context([
            'CREATE TABLE app.p (a INT PRIMARY KEY)', 'CREATE TABLE app.c (x INT, FOREIGN KEY different_index (x) REFERENCES p(a))',
        ]);
        self::assertInstanceOf(AnalysisContext::class, $catalog);
        $parsed = (new Inspection())->inspect('ALTER TABLE app.c DROP FOREIGN KEY C_IBFK_1', $catalog)->statement;
        self::assertInstanceOf(AlterTable::class, $parsed);
        self::assertInstanceOf(DropElement::class, $parsed->commands[0]);
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('c')), ExternalRelationKind::BaseTable);
        $target = (new ForeignKeyRemovalLowering())->constraint($catalog, $owner, $parsed->commands[0]);

        self::assertSame(ForeignKeyRemovalKind::Constraint, $target->kind);
        self::assertSame('c_ibfk_1', $target->name?->value);
    }

    public function testQualifierAcceptsTheExactLegacyOwner(): void
    {
        $owner = new TableIdentity(new TableName(new Identifier('app'), new Identifier('c')), ExternalRelationKind::BaseTable);
        (new ForeignKeyRemovalLowering())->qualifier($owner, new QualifiedName(new Name('c'), new Name('app')), true);

        self::assertSame('c', $owner->name->table->value);
    }
}
