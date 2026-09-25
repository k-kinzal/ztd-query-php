<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[CoversClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[CoversClass(\SqlSemantics\Binding\NullFacts::class)]
#[CoversClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectBinder::class)]
#[CoversClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[CoversClass(\SqlSemantics\Binding\TypeResolution::class)]
#[CoversClass(Binder::class)]
#[CoversClass(SchemaBuilder::class)]
#[CoversClass(\SqlSemantics\Ast\DialectParser::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
#[CoversClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[CoversClass(\SqlSemantics\Binding\BoundRelation::class)]
#[CoversClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[CoversClass(\SqlSemantics\Binding\TableResolver::class)]
#[CoversClass(\SqlSemantics\Model\ColumnBinding::class)]
#[CoversClass(\SqlSemantics\Model\Expression::class)]
#[CoversClass(\SqlSemantics\Model\Join::class)]
#[CoversClass(\SqlSemantics\Model\Ordering::class)]
#[CoversClass(\SqlSemantics\Model\OutputColumn::class)]
#[CoversClass(\SqlSemantics\Model\BoundQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
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
final class ConstraintReaderTest extends TestCase
{
    public function testReadNamedForeignKey(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER, CONSTRAINT self_ref FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE DEFERRABLE)')->tables[0];
        self::assertSame('self_ref', $table->constraints[0]->name);
        self::assertSame(['id'], $table->constraints[0]->localColumns());
        self::assertStringContainsString('DEFERRABLE', $table->constraints[0]->source->toString());
    }

    public function testReferencesPreservesCompositeForeignKeyOrder(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER, parent_id INTEGER, FOREIGN KEY (id, parent_id) REFERENCES other.users (parent_id, id))')->tables[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\ForeignKey::class, $table->constraints[0]);
        self::assertSame(['other', 'users'], $table->constraints[0]->referencedTable->parts);
        self::assertSame(['parent_id', 'id'], $table->constraints[0]->referencedColumns);
    }

    public function testReferencesSeparatesDeleteColumnsFromOmittedReferenceColumns(): void
    {
        $constraint = (new SchemaBuilder(Dialect::PostgreSql))->build('create table t(tenant integer, id integer, foreign key(tenant,id) references p on delete set null(id))')->tables[0]->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\ForeignKey::class, $constraint);
        self::assertSame(['p'], $constraint->referencedTable->parts);
        self::assertSame([], $constraint->referencedColumns);
        self::assertSame(['id'], $constraint->deleteColumns);
    }

    public function testColumnsSkipsPrefixLengthsAndDirectionsInPrimaryKeys(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(name VARCHAR(10), n INT, PRIMARY KEY (name(3), n DESC))')->tables[0];
        self::assertSame(['name', 'n'], $table->constraints[0]->localColumns());
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $table->columns[0]->nullability);
    }

    public function testColumnsSkipsPrefixLengthsAndDirectionsInUniqueKeys(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t(name VARCHAR(10), n INT, UNIQUE KEY (n DESC, name(3)))');
        self::assertSame('CREATE TABLE `t`(`name` varchar(10), `n` integer, UNIQUE(`n` DESC, `name`(3)))', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testColumnsSkipsExpressionKeys(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, UNIQUE KEY ((a + 1)), UNIQUE KEY (b))')->tables[0];
        self::assertSame([], $table->constraints[0]->localColumns());
        self::assertSame(['b'], $table->constraints[1]->localColumns());
    }

    public function testReadTreatsAConstraintKeywordWithoutANameAsUnnamed(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, CONSTRAINT PRIMARY KEY (a), CONSTRAINT CHECK (a > 0))')->tables[0];
        self::assertSame([null, null], array_map(static fn ($constraint): ?string => $constraint->name, $table->constraints));
        self::assertSame([], $table->indexes);
    }

    public function testReadTreatsALowercaseConstraintKeywordWithoutANameAsUnnamed(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, constraint primary key (a), constraint check (a > 0))')->tables[0];
        self::assertSame([null, null], array_map(static fn ($constraint): ?string => $constraint->name, $table->constraints));
    }

    public function testColumnsKeepsAColumnAfterAnExpressionKey(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT, UNIQUE KEY ((a + 1), b))')->tables[0];
        self::assertSame(['b'], $table->constraints[0]->localColumns());
    }

    public function testReadNamesAColumnReference(): void
    {
        $tables = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE p(id INT PRIMARY KEY); CREATE TABLE t(id INT CONSTRAINT c REFERENCES p(id))')->tables;
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\ForeignKey::class, $tables[1]->constraints[0]);
        self::assertSame('c', $tables[1]->constraints[0]->name);
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.0.44', 'CREATE TABLE t (a INT CHECK (a > 0) NOT ENFORCED, CONSTRAINT c CHECK (a < 9) NOT ENFORCED, CHECK (a <> 5) ENFORCED)', 'CREATE TABLE `t`(`a` integer, CHECK ((`a` > 0)) NOT ENFORCED, CONSTRAINT `c` CHECK ((`a` < 9)) NOT ENFORCED, CHECK ((`a` <> 5)))'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0', 'CREATE TABLE t (a INT, CONSTRAINT c CHECK (a > 0) NOT ENFORCED)', 'CREATE TABLE `t`(`a` integer, CONSTRAINT `c` CHECK ((`a` > 0)) NOT ENFORCED)'])]
    public function testReadKeepsTheEnforcementOfAMySqlCheck(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51', 'CREATE TABLE t (a INT KEY, b INT)', 'CREATE TABLE `t`(`a` integer NOT NULL, `b` integer, PRIMARY KEY(`a`))'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44', 'CREATE TABLE t (a INT NOT NULL KEY)', 'CREATE TABLE `t`(`a` integer NOT NULL, PRIMARY KEY(`a`))'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7', 'CREATE TABLE t (a INT KEY, KEY k (a))', 'CREATE TABLE `t`(`a` integer NOT NULL, PRIMARY KEY(`a`), INDEX `k`(`a`))'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0', 'CREATE TABLE t (a INT UNIQUE KEY)', 'CREATE TABLE `t`(`a` integer, UNIQUE(`a`))'])]
    public function testReadTreatsABareMySqlColumnKeyAsItsPrimaryKey(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($sql)));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['KEY', true, \SqlSemantics\Schema\ConstraintKind::PrimaryKey])]
    #[\PHPUnit\Framework\Attributes\TestWith(['KEY', false, null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['REFERENCES', true, \SqlSemantics\Schema\ConstraintKind::ForeignKey])]
    #[\PHPUnit\Framework\Attributes\TestWith(['COMMENT', true, null])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SERIAL', true, \SqlSemantics\Schema\ConstraintKind::Unique])]
    #[\PHPUnit\Framework\Attributes\TestWith(['SERIAL', false, null])]
    public function testKindClassifiesTheLeadingKeyword(string $keyword, bool $columnAttribute, ?\SqlSemantics\Schema\ConstraintKind $expected): void
    {
        self::assertSame($expected, \SqlSemantics\Ast\ConstraintReader::kind($keyword, $columnAttribute));
    }

    public function testReadKeepsTheBareKeyOfAnAddedMySqlColumn(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)'));
        self::assertSame('ALTER TABLE `t` ADD COLUMN `b` integer PRIMARY KEY', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('ALTER TABLE t ADD COLUMN b INT KEY')));
    }

    public function testReadKeepsTheEnforcementOfAnAddedMySqlCheck(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT)'));
        self::assertSame('ALTER TABLE `t` ADD CONSTRAINT `c` CHECK ((`a` > 0)) NOT ENFORCED', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('ALTER TABLE t ADD CONSTRAINT c CHECK (a > 0) NOT ENFORCED')));
        self::assertSame('ALTER TABLE `t` ADD COLUMN `b` integer CHECK ((`b` > 0)) NOT ENFORCED', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('ALTER TABLE t ADD COLUMN b INT CHECK (b > 0) NOT ENFORCED')));
    }

    public function testReadKeepsNoInheritOfAPostgreSqlCheck(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TABLE t (a int CHECK (a > 0) NO INHERIT, b int CHECK (b > 0), CONSTRAINT c CHECK (a < 9) NO INHERIT)');
        self::assertSame('CREATE TABLE "public"."t"("a" integer, "b" integer, CHECK (("a" > 0)) NO INHERIT, CHECK (("b" > 0)), CONSTRAINT "c" CHECK (("a" < 9)) NO INHERIT)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testReadRejectsNoInheritOnAKey(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (a int, CONSTRAINT k PRIMARY KEY (a) NO INHERIT)');
    }


    public function testCheckAttributesReadsEnforcementAndInheritance(): void
    {
        $mysql = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('CREATE TABLE t (a INT, CONSTRAINT c CHECK (a > 0) NOT ENFORCED)');
        $postgres = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t (a int, CONSTRAINT c CHECK (a > 0) NO INHERIT)');
        self::assertSame([false, false], \SqlSemantics\Ast\ConstraintReader::checkAttributes(\SqlSemantics\Ast\Tree::outer($mysql, ['table_constraint_def'])[0]));
        self::assertSame([true, true], \SqlSemantics\Ast\ConstraintReader::checkAttributes(\SqlSemantics\Ast\Tree::outer($postgres, ['TableConstraint'])[0]));
    }

    public function testReadGivesSerialDefaultValueItsUniqueKey(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (a INT SERIAL DEFAULT VALUE, b INT)')->tables[0];
        self::assertCount(1, $table->constraints);
        self::assertSame(\SqlSemantics\Schema\ConstraintKind::Unique, $table->constraints[0]->kind);
        self::assertSame(['a'], $table->constraints[0]->localColumns());
        self::assertNull($table->constraints[0]->name);
    }
}
