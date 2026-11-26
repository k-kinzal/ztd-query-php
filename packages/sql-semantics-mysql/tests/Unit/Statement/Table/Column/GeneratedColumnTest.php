<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GeneratedColumn::class)]
#[Medium]
final class GeneratedColumnTest extends TestCase
{
    public function testDataTypeAnswersTheType(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT, b BIGINT AS (a) VIRTUAL)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        $specification = $element1->specification;

        self::assertInstanceOf(GeneratedColumn::class, $specification);
        self::assertInstanceOf(Integral::class, $specification->dataType());
        self::assertSame(GeneratedStorage::Virtual, $specification->storage);
    }

    public function testColumnAttributesAnswersTheAttributes(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT, b INT AS (a) NOT NULL COMMENT \'x\')');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        self::assertCount(2, $element1->specification->columnAttributes());
    }

    public function testDeriveSpecificationReportsAMissingColumn(): void
    {
        $create = (new Inspection())->diagnose('CREATE TABLE t (a INT, b INT AS (c))', []);

        self::assertSame('Column c does not exist.', $create->facts->diagnostics[0]->message());
    }

    public function testRenderLeavesOutGeneratedAlways(): void
    {
        self::assertSame('CREATE TABLE t (a TEXT, b TEXT COLLATE utf8mb4_bin AS (a) STORED UNIQUE)', (new Inspection())->diagnose('CREATE TABLE t (a TEXT, b TEXT COLLATE utf8mb4_bin GENERATED ALWAYS AS (a) STORED UNIQUE KEY)')->toString());
    }
}
