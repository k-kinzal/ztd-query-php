<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Listings;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\MetadataColumn;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowColumnsStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowIndexesStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Listings::class)]
#[Medium]
final class ListingsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindNormalizesSynonymsAndFoldsTheDatabaseSelectorAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SHOW /* listing */ FULL TABLES IN app LIKE 'u%'");
        self::assertInstanceOf(ShowTablesStatement::class, $statement);
        self::assertSame('app', $statement->database);
        self::assertTrue($statement->full);
        self::assertSame("SHOW FULL TABLES FROM `app` LIKE 'u%'", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testCatalogResolvesConditionReferencesAgainstTheListingResultFields(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW SCHEMAS WHERE `Database` LIKE 'app%'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        self::assertInstanceOf(ConditionFilter::class, $statement->filter);
        $reference = $statement->filter->condition->inputs()[0] ?? null;
        self::assertInstanceOf(MetadataColumn::class, $reference);
        self::assertSame($statement->scopeId, $reference->scopeId);
        self::assertSame([], $statement->diagnostics);
    }

    public function testDescribedLetsATrailingDatabaseOverrideTheTableQualifier(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, 'main'))->build('CREATE TABLE users(id INT); CREATE TABLE other.users(id INT)'));
        $statement = $binder->bind('SHOW COLUMNS FROM main.users FROM other');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertSame('other', $statement->table->declaration->schema);
        self::assertSame('SHOW COLUMNS FROM `other`.`users`', $statement->toString());
    }

    public function testDescribedResolvesIndexConditionsAgainstTheIndexFields(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW EXTENDED INDEX IN users WHERE Key_name = "PRIMARY"');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        self::assertTrue($statement->extended);
        self::assertInstanceOf(ConditionFilter::class, $statement->condition);
        self::assertInstanceOf(BinaryExpression::class, $statement->condition->condition);
        self::assertSame('Key_name', $statement->condition->condition->inputs()[0]->spelling());
        self::assertSame('SHOW EXTENDED INDEX FROM `users` WHERE (`Key_name` = "PRIMARY")', $statement->toString());
    }

    public function testDescribedDiagnosesAnUnknownTableInsteadOfThrowing(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW COLUMNS FROM missing', strict: false);
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertCount(1, $statement->diagnostics);
        self::assertSame('SHOW COLUMNS FROM `missing`', $statement->toString());
    }
}
