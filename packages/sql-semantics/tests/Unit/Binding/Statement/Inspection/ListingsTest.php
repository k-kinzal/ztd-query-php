<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindListsEachCatalogForm(): array
    {
        return [
            [Dialect::MySql, null, 'SHOW DATABASES', [ShowDatabasesStatement::class, 'SHOW DATABASES']],
            [Dialect::MySql, null, 'SHOW SCHEMAS LIKE \'a%\'', [ShowDatabasesStatement::class, 'SHOW DATABASES LIKE \'a%\'']],
            [Dialect::MySql, null, 'SHOW TABLE STATUS FROM d', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowTableStatusStatement::class, 'SHOW TABLE STATUS FROM `d`']],
            [Dialect::MySql, null, 'SHOW OPEN TABLES FROM d', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowOpenTablesStatement::class, 'SHOW OPEN TABLES FROM `d`']],
            [Dialect::MySql, null, 'SHOW TRIGGERS', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowTriggersStatement::class, 'SHOW TRIGGERS']],
            [Dialect::MySql, null, 'SHOW EVENTS FROM d', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowEventsStatement::class, 'SHOW EVENTS FROM `d`']],
            [Dialect::MySql, null, 'SHOW CHARSET', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowCharacterSetsStatement::class, 'SHOW CHARACTER SET']],
            [Dialect::MySql, null, 'SHOW CHARACTER SET', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowCharacterSetsStatement::class, 'SHOW CHARACTER SET']],
            [Dialect::MySql, null, 'SHOW CHAR SET', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowCharacterSetsStatement::class, 'SHOW CHARACTER SET']],
            [Dialect::MySql, null, 'SHOW COLLATION WHERE Charset = \'utf8mb4\'', [\SqlSemantics\Model\Statement\Inspection\Schema\ShowCollationsStatement::class, 'SHOW COLLATION WHERE (`Charset` = \'utf8mb4\')']],
            [Dialect::MySql, null, 'SHOW COLUMNS FROM t', [ShowColumnsStatement::class, 'SHOW COLUMNS FROM `t`']],
            [Dialect::MySql, null, 'SHOW INDEX FROM t', [ShowIndexesStatement::class, 'SHOW INDEX FROM `t`']],
            [Dialect::MySql, null, 'SHOW TABLES', [ShowTablesStatement::class, 'SHOW TABLES']],
        ];
    }

    #[DataProvider('providerBindListsEachCatalogForm')]
    public function testBindListsEachCatalogForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }
}
