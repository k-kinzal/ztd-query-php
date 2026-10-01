<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\Analysis\RowsReader;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\SemanticGraph;

#[CoversClass(RowsReader::class)]
#[Medium]
final class RowsReaderTest extends TestCase
{
    public function testReadRetainsEveryRowAndExpressionInOrder(): void
    {
        $tree = (new SqliteParser())->parse('VALUES (1, NULL), (2, 3), (4, 5)');
        $rows = (new RowsReader())->read(Tree::outer($tree, ['mvalues'])[0], new Catalog(new SearchPath(new Name('main'))));
        self::assertSame([2, 2, 2], $rows->widths());
        self::assertSame('VALUES (1, NULL), (2, 3), (4, 5)', $rows->toString());
        self::assertTrue((new SemanticGraph())->isSemanticOperation($rows));
    }

    public function testReadKeepsIncompatibleWidthsAsASemanticContradiction(): void
    {
        $tree = (new SqliteParser())->parse('VALUES (1), (2, 3)');
        $rows = (new RowsReader())->read(Tree::outer($tree, ['mvalues'])[0], new Catalog(new SearchPath(new Name('main'))));
        self::assertSame([1, 2], $rows->widths());
    }
}
