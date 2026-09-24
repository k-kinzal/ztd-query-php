<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Filters;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\MetadataColumn;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowColumnsStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowEventsStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Filters::class)]
#[Medium]
final class FiltersTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testRestrictBuildsTheListingWithItsRestrictionAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $pattern = $binder->bind("SHOW EVENTS LIKE 'a''b'");
        $condition = $binder->bind('SHOW EVENTS WHERE Status = "ENABLED"');
        $none = $binder->bind('SHOW EVENTS');
        self::assertInstanceOf(ShowEventsStatement::class, $pattern);
        self::assertInstanceOf(ShowEventsStatement::class, $condition);
        self::assertInstanceOf(ShowEventsStatement::class, $none);
        self::assertInstanceOf(PatternFilter::class, $pattern->filter);
        self::assertInstanceOf(ConditionFilter::class, $condition->filter);
        self::assertNull($none->filter);
        self::assertSame("SHOW EVENTS LIKE 'a''b'", $pattern->toString());
    }

    public function testReadKeepsThePatternSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW TABLES LIKE '%\\_x'");
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertSame("'%\\_x'", $statement->filter->pattern->text);
    }

    public function testConditionResolvesResultLabelsAndDiagnosesOtherNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, 'main'))->build());
        $resolved = $binder->bind('SHOW TABLES WHERE Tables_in_main = "x"');
        $unresolved = $binder->bind('SHOW TABLES WHERE Tables_in_other = "x"', strict: false);
        self::assertInstanceOf(ShowTablesStatement::class, $resolved);
        self::assertInstanceOf(ShowTablesStatement::class, $unresolved);
        self::assertInstanceOf(ConditionFilter::class, $resolved->filter);
        self::assertInstanceOf(ConditionFilter::class, $unresolved->filter);
        self::assertInstanceOf(MetadataColumn::class, $resolved->filter->condition->inputs()[0]);
        self::assertInstanceOf(UnresolvedColumnReference::class, $unresolved->filter->condition->inputs()[0]);
        self::assertSame([], $resolved->diagnostics);
        self::assertCount(1, $unresolved->diagnostics);
    }

    public function testDatabaseReadsFromAndInAlike(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $from = $binder->bind('SHOW TABLES FROM `a b`');
        $in = $binder->bind('SHOW TABLES IN `a b`');
        self::assertInstanceOf(ShowTablesStatement::class, $from);
        self::assertInstanceOf(ShowTablesStatement::class, $in);
        self::assertSame('a b', $from->database);
        self::assertSame('a b', $in->database);
    }

    public function testTableResolvesAgainstTheSchemaWithoutAnAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, 'main'))->build('CREATE TABLE users(id INT)')))->bind('SHOW COLUMNS IN users IN main');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertNull($statement->table->alias);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertSame($statement->scopeId, $statement->table->scopeId);
    }
}
