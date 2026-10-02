<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(C\Query\RowDefinition::class)]
#[Small]
final class RowDefinitionTest extends TestCase
{
    public function testInconsistentWidthsRemainAStructuredSqlDiagnostic(): void
    {
        $context = new Catalog(new SearchPath(new Name('main')));
        $input = new C\Query\RowsDefinition(new C\Query\RowDefinition(new E\SqliteInteger(new UnsignedInteger('1'))), new C\Query\RowDefinition(new E\SqliteInteger(new UnsignedInteger('2')), new E\SqliteInteger(new UnsignedInteger('3'))));
        $rows = (new C\RowsConstruction())->derive($input, $context);
        self::assertSame([1, 2], $rows->widths());
        self::assertSame('VALUES (1), (2, 3)', $rows->toString());
    }
}
