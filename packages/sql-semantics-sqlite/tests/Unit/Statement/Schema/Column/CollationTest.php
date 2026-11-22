<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Collation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Collation::class)]
#[Medium]
final class CollationTest extends TestCase
{
    public function testDeriveConstraintRecordsNothingForAnUnknownCollation(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE t (a COLLATE no_such_collation)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheCollationName(): void
    {
        $operation = (new Inspection())->inspect('create table t (a text collate "RTRIM")');
        $statement = $operation->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(Collation::class, $statement->columns[0]->constraints[0]);
        self::assertSame('RTRIM', $statement->columns[0]->constraints[0]->name->value);
        self::assertSame('CREATE TABLE t (a text COLLATE RTRIM)', $operation->toString());
    }
}
