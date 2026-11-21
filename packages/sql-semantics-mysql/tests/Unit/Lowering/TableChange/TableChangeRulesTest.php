<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\TableChangeRules;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(TableChangeRules::class)]
#[Medium]
final class TableChangeRulesTest extends TestCase
{
    public function testStatementLowersAStatementNode(): void
    {
        self::assertSame('TRUNCATE TABLE t', (new Semantics())->analyze('TRUNCATE t')->toString());
    }

    public function testDefinitionLowersARoutedProduction(): void
    {
        self::assertSame('ALTER TABLE t FORCE', (new Semantics('mysql-5.7.44'))->analyze('alter table t force')->toString());
    }

    public function testPartitioningLowersTheClauseOfCreateTable(): void
    {
        self::assertSame('CREATE TABLE t (a INT) PARTITION BY KEY (a)', (new Semantics('mysql-5.7.44'))->analyze('CREATE TABLE t (a INT) PARTITION BY KEY (a)')->toString());
    }

    public function testAlterOptionsLowersTheOptionsOfDropIndex(): void
    {
        self::assertSame('DROP INDEX i ON t ALGORITHM = inplace', (new Semantics())->analyze('drop index i on t algorithm = inplace')->toString());
    }

    public function testPartitionNamesLowersAllOrNames(): void
    {
        self::assertSame('ALTER TABLE t ANALYZE PARTITION p, q', (new Semantics())->analyze('alter table t analyze partition p, q')->toString());
    }
}
