<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;

#[CoversClass(TableProblems::class)]
#[Medium]
final class TableProblemsTest extends TestCase
{
    public function testReportReportsANullablePrimaryKeyFromMySql57(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');
        $legacy = (new Semantics(Dialect::MySql, '5.6.51'))->analyze('CREATE TABLE t (a INT NULL, PRIMARY KEY (a))');

        self::assertInstanceOf(NullablePrimaryKey::class, $create->facts->diagnostics[0]);
        self::assertSame([], $legacy->facts->diagnostics);
    }

    public function testDuplicatesReportsARepeatedColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, A INT)');

        self::assertInstanceOf(DuplicateColumn::class, $create->facts->diagnostics[0]);
    }

    public function testKeyColumnsReportsAMissingKeyColumn(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, UNIQUE (b))');

        self::assertInstanceOf(UnknownKeyColumn::class, $create->facts->diagnostics[0]);
    }
}
