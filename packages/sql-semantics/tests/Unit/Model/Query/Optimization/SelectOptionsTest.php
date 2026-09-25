<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Optimization\SelectOption;
use SqlSemantics\Model\Query\Optimization\SelectOptions;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectOptions::class)]
#[Medium]
final class SelectOptionsTest extends TestCase
{
    public function testValidateAcceptsDistinctMySqlOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT SQL_CACHE HIGH_PRIORITY HIGH_PRIORITY 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([SelectOption::Cache, SelectOption::HighPriority], $statement->options);
        SelectOptions::validate($statement->options, $statement->origin);
        self::assertSame('SELECT SQL_CACHE HIGH_PRIORITY 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @param list<SelectOption> $options
     */
    #[TestWith([Dialect::PostgreSql, [SelectOption::HighPriority]])]
    #[TestWith([Dialect::MySql, [SelectOption::HighPriority, SelectOption::HighPriority]])]
    #[TestWith([Dialect::MySql, [SelectOption::Cache, SelectOption::NoCache]])]
    public function testValidateRejectsForeignRepeatedOrConflictingOptions(Dialect $dialect, array $options): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1');
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        SelectOptions::validate($options, $statement->origin);
    }

    public function testValidateRejectsSqlCacheFromMySql80(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('SELECT 1');
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        SelectOptions::validate([SelectOption::Cache], $statement->origin);
    }
}
