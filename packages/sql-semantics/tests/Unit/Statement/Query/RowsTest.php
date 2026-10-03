<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\NullConstant;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(Rows::class)]
#[Small]
final class RowsTest extends TestCase
{
    public function testToStringReconstructsRowsWithoutEvaluatingThem(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $row = new Row($scope, new NullConstant());
        $rows = new Rows($row, $row);
        self::assertSame([$row, $row], $rows->rows);
        self::assertSame('VALUES (NULL), (NULL)', $rows->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($rows));
    }

    public function testWidthsRetainsGrammarValidIncompatibleRows(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $rows = new Rows(new Row($scope, new NullConstant()), new Row($scope, new NullConstant(), new NullConstant()));
        self::assertSame([1, 2], $rows->widths());
    }
}
