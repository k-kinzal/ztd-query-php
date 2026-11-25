<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(References::class)]
#[Medium]
final class ReferencesTest extends TestCase
{
    public function testDeriveReferencesResolvesTheTableBeingDefined(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE c (id INT, p INT REFERENCES c (id))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        $specification1 = $element1->specification;
        self::assertInstanceOf(OrdinaryColumn::class, $specification1);
        $references = $specification1->references;

        self::assertInstanceOf(References::class, $references);
        $table = $create->facts->relation($references)->table;
        self::assertInstanceOf(DeclaredTable::class, $table);
        self::assertSame($create->declarations()[0], $table->table);
    }

    public function testDeriveReferencesResolvesAnotherTable(): void
    {
        $semantics = new Inspection();
        $parent = $semantics->inspect('CREATE TABLE p (id INT)');
        $child = $semantics->inspect('CREATE TABLE c (p INT, FOREIGN KEY (p) REFERENCES p (id))', [$parent]);
        $childStatement = $child->statement;
        self::assertInstanceOf(CreateTable::class, $childStatement);
        $element1 = $childStatement->elements[1];
        self::assertInstanceOf(ForeignKey::class, $element1);
        $table = $child->facts->relation($element1->references)->table;

        self::assertInstanceOf(DeclaredTable::class, $table);
        self::assertSame($parent->declarations()[0], $table->table);
    }

    public function testRenderWritesTheClause(): void
    {
        self::assertSame('CREATE TABLE c (p INT REFERENCES shop.p (id, x) MATCH PARTIAL ON DELETE RESTRICT ON UPDATE NO ACTION)', (new Inspection())->inspect('CREATE TABLE c (p INT REFERENCES shop.p (id, x) MATCH PARTIAL ON DELETE RESTRICT ON UPDATE NO ACTION)')->toString());
    }

    public function testDeriveAlterReferenceUsesThePersistentChildDatabase(): void
    {
        $semantics = new Inspection();
        $parent = $semantics->inspect('CREATE TABLE warehouse.p (id INT PRIMARY KEY)');
        $temporary = $semantics->inspect('CREATE TEMPORARY TABLE warehouse.p (other INT)');
        $child = $semantics->inspect('CREATE TABLE warehouse.c (pid INT)');
        $change = $semantics->inspect('ALTER TABLE warehouse.c ADD FOREIGN KEY (pid) REFERENCES p (id)', [$parent, $temporary, $child]);
        self::assertInstanceOf(AlterTable::class, $change->statement);
        $command = $change->statement->commands[0];
        self::assertInstanceOf(AddConstraint::class, $command);
        self::assertInstanceOf(ForeignKey::class, $command->element);
        $bound = $change->facts->relation($command->element->references)->table;

        self::assertInstanceOf(DeclaredTable::class, $bound);
        self::assertSame($parent->declarations()[0], $bound->table);
    }
}
