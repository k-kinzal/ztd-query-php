<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\ZtdQuery\Platform\Postgres\Session\SchemaInitializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\FromClause::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Parsing\Relation\RelationReference::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlColumnTypeMapper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlForeignKeyDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlIdentifierQuoter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlLexerProfile::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlPartitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlPartitionReflector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSchemaParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSchemaReflector::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlSelectRelationParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\PgSqlViewDefinitionParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Catalog\PartitionKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Catalog\TableQueries::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Column\ColumnDefinitionSql::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Column\NativeTypeSql::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\ForeignKeyRow::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\ForeignKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\IndexDefinitions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\IndexRows::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\PrimaryColumns::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Reflection\Key\UniqueIndexes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\ColumnTypeDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableBody::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Definition\TableFields::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionEntry::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\ForeignKey\DefinitionTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\BoundPredicate::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Schema\Partition\ClauseTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\Postgres\Sql\Lexing\QuotedSpan::class)]
final class SchemaInitializerTest extends TestCase
{
    public function testPopulateRegistersColumnsPartialIndexesAndPartitionKeys(): void
    {
        $connection = new \Tests\Fake\FakeSequentialConnection([
            new \Tests\Fake\FakeStatement([['table_name' => 'users']]),
            new \Tests\Fake\FakeStatement([['column_name' => 'id', 'data_type' => 'integer', 'is_nullable' => 'NO']]),
            new \Tests\Fake\FakeStatement([['column_name' => 'id']]),
            new \Tests\Fake\FakeStatement([['constraint_name' => 'positive_id', 'column_name' => 'id', 'predicate' => 'id > 0']]),
            new \Tests\Fake\FakeStatement([]),
            new \Tests\Fake\FakeStatement([['table_name' => 'users', 'partition_key' => 'RANGE (id)']]),
            new \Tests\Fake\FakeStatement([]),
        ]);
        $registry = new \ZtdQuery\Schema\TableDefinitionRegistry();
        (new \ZtdQuery\Platform\Postgres\Session\SchemaInitializer())->populate($connection, new \ZtdQuery\Platform\Postgres\PgSqlSchemaReflector($connection), new \ZtdQuery\Platform\Postgres\PgSqlSchemaParser(), $registry);
        $definition = $registry->get('users');
        self::assertNotNull($definition);
        self::assertSame(['id'], $definition->primaryKeys);
        self::assertSame('id > 0', $definition->partialUniqueIndexes['positive_id']->predicate);
        self::assertSame(['id'], $definition->partitionKey?->expressions);
    }
}
