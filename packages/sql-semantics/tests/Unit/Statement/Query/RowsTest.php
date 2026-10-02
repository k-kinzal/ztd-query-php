<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction\Query\RowDefinition;
use SqlSemantics\Statement\Construction\Query\RowsDefinition;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Rows::class)]
#[Small]
final class RowsTest extends TestCase
{
    public function testContextRetainsTheSuppliedSnapshot(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $rows = new Rows($catalog, new RowsDefinition(new RowDefinition(new NullConstant())));
        self::assertSame($catalog, $rows->context());
        self::assertNull($rows->scope->parent);
    }

    public function testProfileComesFromTheSuppliedSnapshot(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')));
        $rows = new Rows($catalog, new RowsDefinition(new RowDefinition(new NullConstant())));
        self::assertSame($catalog->profile, $rows->profile());
    }

    public function testToStringReconstructsRowsWithoutEvaluatingThem(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $row = new RowDefinition(new NullConstant());
        $rows = new Rows($scope->catalog, new RowsDefinition($row, $row));
        self::assertCount(2, $rows->rows);
        self::assertNotSame($rows->rows[0], $rows->rows[1]);
        self::assertSame($rows->scope, $rows->rows[0]->scope);
        self::assertSame($rows->scope, $rows->rows[1]->scope);
        self::assertSame('VALUES (NULL), (NULL)', $rows->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($rows));
    }

    public function testWidthsRetainsGrammarValidIncompatibleRows(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $rows = new Rows($scope->catalog, new RowsDefinition(new RowDefinition(new NullConstant()), new RowDefinition(new NullConstant(), new NullConstant())));
        self::assertSame([1, 2], $rows->widths());
    }
}
