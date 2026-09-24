<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;
use SqlSemantics\Model\Statement\Inspection\Session\ShowStatusStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowStatusStatement::class)]
#[Medium]
final class ShowStatusStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResultColumnsListNameAndValueAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SHOW GLOBAL STATUS LIKE 'a%'");
        self::assertInstanceOf(ShowStatusStatement::class, $statement);
        self::assertSame(VariableScope::Global, $statement->scope);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertSame(['Variable_name', 'Value'], array_column($statement->resultColumns(), 'name'));
        self::assertSame("SHOW GLOBAL STATUS LIKE 'a%'", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    #[TestWith(['SHOW STATUS'])]
    #[TestWith(['SHOW LOCAL STATUS'])]
    #[TestWith(['SHOW SESSION STATUS'])]
    public function testSessionSpellingsNormalizeToTheImplicitScope(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(ShowStatusStatement::class, $statement);
        self::assertSame(VariableScope::Session, $statement->scope);
        self::assertSame('SHOW STATUS', $statement->toString());
    }

    public function testWithScopeReplacesTheScopeImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW STATUS');
        self::assertInstanceOf(ShowStatusStatement::class, $statement);
        $changed = $statement->withScope(VariableScope::Global);
        self::assertNotSame($statement, $changed);
        self::assertSame(VariableScope::Session, $statement->scope);
        self::assertSame('SHOW GLOBAL STATUS', $changed->toString());
    }

    public function testWithFilterBindsAConditionOverTheResultFields(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SHOW STATUS LIKE 'x'");
        $other = $binder->bind("SHOW STATUS WHERE Variable_name = 'y'");
        self::assertInstanceOf(ShowStatusStatement::class, $statement);
        self::assertInstanceOf(ShowStatusStatement::class, $other);
        self::assertInstanceOf(ConditionFilter::class, $other->filter);
        $changed = $statement->withFilter($other->filter);
        self::assertNotSame($statement, $changed);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertSame("SHOW STATUS WHERE (`Variable_name` = 'y')", $changed->toString());
        self::assertSame('SHOW STATUS', $changed->withFilter(null)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW GLOBAL STATUS LIKE 'a'");
        self::assertInstanceOf(ShowStatusStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->scope, $statement->filter], [$copy->scope, $copy->filter]);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ShowStatusStatement($statement->origin);
    }
}
