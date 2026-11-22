<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\ImplicitCheckLayout;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnCheck;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ImplicitCheckLayout::class)]
#[Medium]
final class ImplicitCheckLayoutTest extends TestCase
{
    public function testCaptureRetainsOnlyTheObservableRegion(): void
    {
        $statement = (new Inspection())->diagnose('CREATE TABLE t (a CHECK (  A ==001 ))')->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->columns[0]->constraints[0];
        self::assertInstanceOf(ColumnCheck::class, $check);

        self::assertSame('A ==001', $check->labelLayout?->text());
    }
}
