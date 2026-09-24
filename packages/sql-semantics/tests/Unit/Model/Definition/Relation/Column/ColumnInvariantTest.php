<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(Relation\Column\ColumnInvariant::class)]
#[Medium]
final class ColumnInvariantTest extends TestCase
{
    public function testColumnRejectsAnEmptyName(): void
    {
        Relation\Column\ColumnInvariant::column('id');
        $this->expectException(InvalidStructure::class);
        Relation\Column\ColumnInvariant::column('');
    }

    public function testExpressionRequiresThePostgreSqlDialect(): void
    {
        Relation\Column\ColumnInvariant::expression(null);
        Relation\Column\ColumnInvariant::expression(\SqlSemantics\Model\Expression::literal(1, Dialect::PostgreSql));
        $this->expectException(InvalidStructure::class);
        Relation\Column\ColumnInvariant::expression(\SqlSemantics\Model\Expression::literal(1, Dialect::Sqlite));
    }
}
