<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\KeywordAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OrdinaryColumn::class)]
#[Medium]
final class OrdinaryColumnTest extends TestCase
{
    public function testDataTypeAnswersTheType(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT UNSIGNED)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        $specification = $element0->specification;

        self::assertInstanceOf(OrdinaryColumn::class, $specification);
        self::assertInstanceOf(Integral::class, $specification->dataType());
    }

    public function testColumnAttributesAnswersTheAttributesInOrder(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT NOT NULL NULL)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        $attributes = $element0->specification->columnAttributes();

        self::assertCount(2, $attributes);
        self::assertInstanceOf(KeywordAttribute::class, $attributes[1]);
    }

    public function testDeriveSpecificationDerivesTheAttributes(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT CHECK (a > 0))');

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheTypeTheAttributesAndTheReference(): void
    {
        self::assertSame('CREATE TABLE t (a INT NOT NULL REFERENCES p (id) ON DELETE CASCADE)', (new Inspection())->inspect('CREATE TABLE t (a INT NOT NULL REFERENCES p (id) ON DELETE CASCADE)')->toString());
    }
}
