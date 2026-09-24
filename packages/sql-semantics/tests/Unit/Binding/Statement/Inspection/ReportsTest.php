<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Reports;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Engine\EngineReport;
use SqlSemantics\Model\Query\Inspection\Engine\EngineSelection;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Inspection\Server\ShowEngineReportStatement;
use SqlSemantics\Model\Statement\Inspection\Server\ShowParseTreeStatement;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfilesStatement;
use SqlSemantics\Model\Statement\Inspection\Server\ShowProfileStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Reports::class)]
#[Medium]
final class ReportsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindKeepsProfileOperandsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('SHOW /* stages */ PROFILE SOURCE, CPU FOR QUERY 12 LIMIT 3 OFFSET 1');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertSame([ProfileCategory::Source, ProfileCategory::Cpu], $statement->categories);
        self::assertSame('12', $statement->query?->text);
        self::assertSame(['3', '1'], [$statement->limit?->count->spelling(), $statement->limit?->offset?->spelling()]);
        self::assertSame('SHOW PROFILE SOURCE, CPU FOR QUERY 12 LIMIT 3 OFFSET 1', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
        self::assertInstanceOf(ShowProfilesStatement::class, $binder->bind('SHOW PROFILES'));
    }

    public function testEngineDistinguishesTheAllKeywordFromNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $all = $binder->bind('SHOW ENGINE ALL STATUS');
        $quoted = $binder->bind('SHOW ENGINE `all` LOGS');
        self::assertInstanceOf(ShowEngineReportStatement::class, $all);
        self::assertInstanceOf(ShowEngineReportStatement::class, $quoted);
        self::assertSame([EngineSelection::All, EngineReport::Status], [$all->engine, $all->report]);
        self::assertSame(['all', EngineReport::Logs], [$quoted->engine, $quoted->report]);
    }

    #[TestWith(["SHOW ENGINE '' STATUS"])]
    #[TestWith(['SHOW ENGINE `` MUTEX'])]
    public function testEngineRejectsAnEmptyName(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::EngineName->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql);
    }

    public function testProfileKeepsRepeatedCategoriesInRequestOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE CPU, CPU, MEMORY');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertSame([ProfileCategory::Cpu, ProfileCategory::Cpu, ProfileCategory::Memory], $statement->categories);
        self::assertNull($statement->query);
        self::assertSame('SHOW PROFILE CPU, CPU, MEMORY', $statement->toString());
    }

    public function testLimitReadsACommaWindowAsOffsetThenCount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PROFILE LIMIT 18446744073709551615, ?');
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertInstanceOf(Parameter::class, $statement->limit?->count);
        self::assertSame('18446744073709551615', $statement->limit->offset?->spelling());
        self::assertSame('SHOW PROFILE LIMIT ? OFFSET 18446744073709551615', $statement->toString());
    }

    public function testOptionReadsAKeywordSpelledVariableNameAsAReference(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SHOW PROFILE LIMIT COMMIT OFFSET skip', strict: false);
        self::assertInstanceOf(ShowProfileStatement::class, $statement);
        self::assertInstanceOf(UnresolvedColumnReference::class, $statement->limit?->count);
        self::assertInstanceOf(UnresolvedColumnReference::class, $statement->limit->offset);
        self::assertSame(['COMMIT'], $statement->limit->count->name);
        self::assertCount(2, $statement->diagnostics);
        self::assertSame('SHOW PROFILE LIMIT `COMMIT` OFFSET `skip`', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString(), strict: false)->toString());
    }

    public function testParseTreeBindsTheNestedStatementWithoutExecutingIt(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE users(id INT)')))->bind('SHOW PARSE_TREE DELETE FROM users WHERE id = 1');
        self::assertInstanceOf(ShowParseTreeStatement::class, $statement);
        self::assertSame('DELETE', $statement->statement->kind->value);
        self::assertSame('SHOW PARSE_TREE DELETE FROM `users` WHERE (`id` = 1)', $statement->toString());
    }
}
