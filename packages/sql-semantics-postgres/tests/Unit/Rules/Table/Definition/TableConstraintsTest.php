<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints::class)]
#[Medium]
final class TableConstraintsTest extends TestCase
{
    public function testAdmitsIsTrueForTableConstraints(): void
    {
        self::assertSame([
          0 => true,
          1 => false,
        ], [(new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints())->admits(new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint(true, new \SqlSemantics\Statement\Identifier\Name('i'))), (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints())->admits(new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull())]);
    }

    public function testTypedElementsRefusesAColumnDefinition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n2);
        $n3 = $n2->elements[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition::class, $n3);
        $this->expectExceptionMessage('An element of a typed table or a partition is column options or a table constraint.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints())->typedElements([$n3]);
    }
}
