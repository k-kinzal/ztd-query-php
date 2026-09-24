<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Definition;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\FunctionSignature;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class OptionReaderTest extends TestCase
{
    public function testReadTableOptionsWithoutMixingColumnOptions(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(id INT, name VARCHAR(10) COLLATE utf8mb4_bin COMMENT 'column') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='table'")->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Table\MySqlProperties::class, $table->properties);
        self::assertSame('InnoDB', $table->properties->engine);
        self::assertSame('utf8mb4', $table->properties->characterSet);
        self::assertSame(\SqlSemantics\Schema\Table\RowFormat::Dynamic, $table->properties->rowFormat);
        self::assertSame('table', $table->properties->comment);
        self::assertSame(['utf8mb4_bin'], $table->columns[1]->attributes->collation?->parts);
        self::assertSame('column', $table->columns[1]->attributes->comment);
    }

    public function testColumnReadsIdentityAndCharacterSet(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER GENERATED ALWAYS AS IDENTITY (START WITH 5 INCREMENT BY 2), name TEXT COLLATE "C")')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\IdentityColumn::class, $table->columns[0]->generation);
        self::assertSame(\SqlSemantics\Schema\Column\IdentityMode::Always, $table->columns[0]->generation->mode);
        self::assertSame('5', $table->columns[0]->generation->sequence->start?->text);
        self::assertSame('2', $table->columns[0]->generation->sequence->increment?->text);
        self::assertSame(['C'], $table->columns[1]->attributes->collation?->parts);
        $mysql = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(10) CHARACTER SET utf8mb4)')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\AutoIncrementColumn::class, $mysql->columns[0]->generation);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\StringStorage::class, $mysql->columns[1]->type->identity);
        self::assertSame('utf8mb4', $mysql->columns[1]->type->identity->characterSet);
    }

    public function testOptionReadsFlagsAndStorageParameters(): void
    {
        $table = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER PRIMARY KEY) WITHOUT ROWID, STRICT')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Table\SqliteProperties::class, $table->properties);
        self::assertTrue($table->properties->withoutRowId);
        self::assertTrue($table->properties->strict);
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER) USING heap WITH (fillfactor=80) TABLESPACE fast')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Table\PostgreSqlProperties::class, $table->properties);
        self::assertSame('heap', $table->properties->accessMethod);
        self::assertSame('fast', $table->properties->tablespace);
        self::assertSame(['fillfactor'], $table->properties->storageParameters[0]->name->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $table->properties->storageParameters[0]->value);
        self::assertSame('80', $table->properties->storageParameters[0]->value->spelling());
    }

    public function testValueDecodesQuotedValues(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(id INT COMMENT 'it''s a column') COMMENT='it''s a table'")->tables[0];
        self::assertSame("it's a column", $table->columns[0]->attributes->comment);
        self::assertInstanceOf(\SqlSemantics\Schema\Table\MySqlProperties::class, $table->properties);
        self::assertSame("it's a table", $table->properties->comment);
    }

    public function testColumnReadsNumericAndGeneratedStorageOptions(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT UNSIGNED ZEROFILL, n INT GENERATED ALWAYS AS (id+1) STORED)')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\IntegerStorage::class, $table->columns[0]->type->identity);
        self::assertTrue($table->columns[0]->type->identity->unsigned);
        self::assertTrue($table->columns[0]->attributes->zeroFill);
        self::assertInstanceOf(\SqlSemantics\Schema\Column\ComputedColumn::class, $table->columns[1]->generation);
        self::assertSame('stored', $table->columns[1]->generation->storage->value);
    }


    public function testReadKeepsListsFlagsAndQuotedOptionNames(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TEMP TABLE t(id INTEGER) WITH ("fillfactor"=80) ON COMMIT DELETE ROWS')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Table\PostgreSqlProperties::class, $table->properties);
        self::assertSame(\SqlSemantics\Schema\Table\Persistence::Temporary, $table->properties->persistence);
        self::assertSame(\SqlSemantics\Schema\Table\CommitAction::DeleteRows, $table->properties->onCommit);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $table->properties->storageParameters[0]->value);
        self::assertSame('80', $table->properties->storageParameters[0]->value->spelling());
    }

    public function testColumnKeepsByDefaultIdentityAndVirtualStorage(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER GENERATED BY DEFAULT AS IDENTITY)')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\IdentityColumn::class, $table->columns[0]->generation);
        self::assertSame('by-default', $table->columns[0]->generation->mode->value);
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT GENERATED ALWAYS AS (id+1) VIRTUAL)')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\ComputedColumn::class, $table->columns[1]->generation);
        self::assertSame('virtual', $table->columns[1]->generation->storage->value);
    }


    public function testReadIfNotExistsAndUniqueNullTreatment(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER); CREATE INDEX IF NOT EXISTS ix ON t(id)');
        $statement = (new Binder($schema))->bind('CREATE INDEX IF NOT EXISTS ix ON t(id)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateIndexStatement::class, $statement);
        self::assertTrue($statement->ifNotExists);
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, UNIQUE NULLS NOT DISTINCT(id))');
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\UniqueKey::class, $schema->tables[0]->constraints[0]);
        self::assertFalse($schema->tables[0]->constraints[0]->nullsDistinct);
    }

    public function testReadDefaultCharacterSetAndCollationAliases(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_bin')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Table\MySqlProperties::class, $table->properties);
        self::assertSame('utf8mb4', $table->properties->characterSet);
        self::assertSame('utf8mb4_bin', $table->properties->collation);
    }


    public function testNameRetainsQualifiedStorageParameterNames(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER) WITH (toast.autovacuum_enabled=false, "toast"."autovacuum_vacuum_threshold"=20)')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Table\PostgreSqlProperties::class, $table->properties);
        self::assertSame(['toast', 'autovacuum_enabled'], $table->properties->storageParameters[0]->name->parts);
        self::assertSame(['toast', 'autovacuum_vacuum_threshold'], $table->properties->storageParameters[1]->name->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $table->properties->storageParameters[0]->value);
        self::assertSame('false', $table->properties->storageParameters[0]->value->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $table->properties->storageParameters[1]->value);
        self::assertSame('20', $table->properties->storageParameters[1]->value->spelling());
    }

    /**
     * @return iterable<string, array{list<string>, array<string, string|bool|list<string>>}>
     */
    public static function providerOption(): iterable
    {
        yield 'no tokens' => [[], []];
        yield 'without rowid in any case' => [['without', 'rowid'], ['without_rowid' => true]];
        yield 'without another word' => [['WITHOUT', 'X'], ['without' => 'X']];
        yield 'nulls not distinct' => [['NULLS', 'not', 'DISTINCT'], ['nulls_distinct' => false]];
        yield 'nulls distinct' => [['NULLS', 'DISTINCT'], ['nulls_distinct' => true]];
        yield 'if not exists in any case' => [['if', 'not', 'exists'], ['if_not_exists' => true]];
        yield 'if exists' => [['IF', 'EXISTS'], ['if' => 'EXISTS']];
        yield 'assigned value' => [['ENGINE', '=', 'InnoDB'], ['engine' => 'InnoDB']];
        yield 'flag' => [['STRICT'], ['strict' => true]];
        yield 'list' => [['X', '(', 'a', ',', 'b', ')'], ['x' => ['a', 'b']]];
    }

    /**
     * @param list<string> $texts
     * @param array<string, string|bool|list<string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerOption')]
    public function testOptionReadsTheNameAndTheValue(array $texts, array $expected): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), $texts);
        self::assertSame($expected, \SqlSemantics\Ast\Definition\OptionReader::option($tokens, new \SqlSemantics\Ast\Identifiers(Dialect::MySql)));
    }

    /**
     * @return iterable<string, array{non-empty-list<string>, array{string, int}}>
     */
    public static function providerName(): iterable
    {
        yield 'default character set' => [['default', 'character', 'set', '=', 'utf8'], ['character_set', 3]];
        yield 'character set' => [['CHARACTER', 'SET', 'utf8'], ['character_set', 2]];
        yield 'default charset' => [['DEFAULT', 'CHARSET', 'utf8'], ['character_set', 2]];
        yield 'charset' => [['CHARSET', 'utf8'], ['character_set', 1]];
        yield 'default collate' => [['DEFAULT', 'COLLATE', 'c'], ['collation', 2]];
        yield 'collate' => [['COLLATE', 'c'], ['collation', 1]];
        yield 'start with' => [['START', 'WITH', '5'], ['start', 2]];
        yield 'increment by' => [['INCREMENT', 'BY', '2'], ['increment', 2]];
        yield 'temp' => [['TEMP'], ['temporary', 1]];
        yield 'bracketed name' => [['[Fill]', '=', '1'], ['Fill', 1]];
        yield 'qualified name' => [['toast', '.', 'enabled', '=', 'false'], ['toast.enabled', 3]];
        yield 'trailing dot' => [['toast', '.'], ['toast', 1]];
    }

    /**
     * @param non-empty-list<string> $texts
     * @param array{string, int} $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerName')]
    public function testNameNormalizesTheOptionName(array $texts, array $expected): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), $texts);
        self::assertSame($expected, \SqlSemantics\Ast\Definition\OptionReader::name($tokens, new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite)));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(["'it''s'", "it's"])]
    #[\PHPUnit\Framework\Attributes\TestWith(['[x y]', 'x y'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['plain', 'plain'])]
    public function testValueDecodesTheQuoting(string $text, string $expected): void
    {
        self::assertSame($expected, \SqlSemantics\Ast\Definition\OptionReader::value(new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite)));
    }

    public function testColumnReadsALowercaseAutoIncrement(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT auto_increment PRIMARY KEY)')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Column\AutoIncrementColumn::class, $table->columns[0]->generation);
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testReadKeepsTheFullTextParser(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE p (b TEXT)'));
        $expected = "CREATE FULLTEXT INDEX `ft` ON `p`(`b`) COMMENT 'x' WITH PARSER `ngram`";
        self::assertSame($expected, $binder->bind("CREATE FULLTEXT INDEX ft ON p (b) WITH PARSER ngram COMMENT 'x'")->toString());
        self::assertSame('CREATE TABLE `u`(`b` text, FULLTEXT INDEX `ft`(`b`) WITH PARSER `ngram`)', $binder->bind('CREATE TABLE u (b TEXT, FULLTEXT KEY ft (b) WITH PARSER ngram)')->toString());
    }
}
