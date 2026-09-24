<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DuplicateRows::class)]
#[Medium]
final class DuplicateRowsTest extends TestCase
{
    public function testBindingReadsTheKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' REPLACE INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame(DuplicateRows::Replace, $statement->duplicates);
    }
}
