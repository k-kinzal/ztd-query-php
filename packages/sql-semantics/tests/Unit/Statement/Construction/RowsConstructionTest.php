<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\RowsConstruction::class)]
#[Small]
final class RowsConstructionTest extends TestCase
{
    public function testDeriveCreatesOneNewScopeForAllRowsWithoutTargetVisibility(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')), complete: false);
        $input = new C\Query\RowsDefinition(new C\Query\RowDefinition(new C\Expression\ColumnUse(new Name('id'))), new C\Query\RowDefinition(new E\NullConstant()));
        $rows = (new C\RowsConstruction())->derive($input, $context);
        self::assertSame([], $rows->scope->tables);
        self::assertSame($rows->rows[0]->scope, $rows->rows[1]->scope);
        self::assertSame(\SqlSemantics\Statement\Type\Invalid::MissingColumn, $rows->rows[0]->expressions[0]->type());
        self::assertSame('VALUES (id), (NULL)', $rows->toString());
    }
}
