<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(References::class)]
#[Medium]
final class ReferencesTest extends TestCase
{
    public function testDeriveReferencesResolvesTheTableBeingDefined(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE c (id INT, p INT REFERENCES c (id))');
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
        $semantics = new Semantics(Dialect::MySql);
        $parent = $semantics->analyze('CREATE TABLE p (id INT)');
        $child = $semantics->analyze('CREATE TABLE c (p INT, FOREIGN KEY (p) REFERENCES p (id))', [$parent]);
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
        self::assertSame('CREATE TABLE c (p INT REFERENCES shop.p (id, x) MATCH PARTIAL ON DELETE RESTRICT ON UPDATE NO ACTION)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE c (p INT REFERENCES shop.p (id, x) MATCH PARTIAL ON DELETE RESTRICT ON UPDATE NO ACTION)')->toString());
    }
}
