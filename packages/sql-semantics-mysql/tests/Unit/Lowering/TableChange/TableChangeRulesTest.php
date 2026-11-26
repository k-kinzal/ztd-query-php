<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\TableChangeRules;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableChangeRules::class)]
#[Medium]
final class TableChangeRulesTest extends TestCase
{
    public function testStatementLowersAStatementNode(): void
    {
        self::assertSame('TRUNCATE TABLE t', (new Inspection())->inspect('TRUNCATE t')->toString());
    }

    public function testDefinitionLowersARoutedProduction(): void
    {
        self::assertSame('ALTER TABLE t FORCE', (new Inspection('mysql-5.7.44'))->inspect('alter table t force')->toString());
    }

    public function testPartitioningLowersTheClauseOfCreateTable(): void
    {
        self::assertSame('CREATE TABLE t (a INT) PARTITION BY KEY (a)', (new Inspection('mysql-5.7.44'))->inspect('CREATE TABLE t (a INT) PARTITION BY KEY (a)')->toString());
    }

    public function testAlterOptionsLowersTheOptionsOfDropIndex(): void
    {
        self::assertSame('DROP INDEX i ON t ALGORITHM = inplace', (new Inspection())->inspect('drop index i on t algorithm = inplace')->toString());
    }

    public function testPartitionNamesLowersAllOrNames(): void
    {
        self::assertSame('ALTER TABLE t ANALYZE PARTITION p, q', (new Inspection())->inspect('alter table t analyze partition p, q')->toString());
    }
}
