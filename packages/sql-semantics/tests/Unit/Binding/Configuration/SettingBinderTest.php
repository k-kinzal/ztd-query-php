<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Binder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\FromBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Scope::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\Medium]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
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
final class SettingBinderTest extends TestCase
{
    public function testBindRetainsScopeNameAndValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET LOCAL work_mem='64MB'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $statement->settings[0]);
        self::assertSame(['work_mem'], $statement->settings[0]->name);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $statement->settings[0]);
        self::assertSame('local', $statement->settings[0]->scope->value);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertSame("'64MB'", $statement->settings[0]->values[0]->spelling());
    }
    public function testSettingPreservesMysqlAssignmentOrderAndScopes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET SESSION sql_mode='ANSI', @n=1, @@GLOBAL.max_connections=200");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertSame([['sql_mode'], ['n'], ['max_connections']], array_column($statement->settings, 'name'));
        self::assertSame(['session', 'user', 'global'], array_map(static fn ($item) => ($item instanceof \SqlSemantics\Model\Configuration\Setting ? $item : throw new LogicException('setting'))->scope->value, $statement->settings));
    }
    public function testMakeKeepsValueListsAndDefault(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SET search_path TO public, example');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertSame(['public','example'], array_map(static fn ($value) => $value->spelling(), $statement->settings[0]->values));
        $default = $binder->bind('SET work_mem TO DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $default);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\DefaultSetting::class, $default->settings[0]);
        $current = $binder->bind('SET work_mem FROM CURRENT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $current);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\CurrentSetting::class, $current->settings[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetAllSettingsStatement::class, $binder->bind('RESET ALL'));
    }
    public function testPragmaRetainsQualifiedNameAndReadMode(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $statement = $binder->bind('PRAGMA main.cache_size=-2000');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement::class, $statement);
        self::assertSame(['main', 'cache_size'], $statement->name->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Pragma\NumericArgument::class, $statement->value);
        self::assertSame(\SqlSemantics\Model\Configuration\Pragma\Sign::Negative, $statement->value->sign);
        $read = $binder->bind('PRAGMA cache_size');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ReadPragmaStatement::class, $read);
        self::assertSame(['cache_size'], $read->name->parts);
    }

    public function testScopeNormalizesMysqlLocalWithoutDroppingQualifiedNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $local = $binder->bind('SET LOCAL sql_mode=DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $local);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $local->settings[0]);
        self::assertSame('session', $local->settings[0]->scope->value);
        $component = $binder->bind('SET @@component.variable=1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $component);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $component->settings[0]);
        self::assertSame(['component','variable'], $component->settings[0]->name);
    }
    public function testSettingResetsPersistedVariablesWithoutRequiringAName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $all = $binder->bind('RESET PERSIST');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetAllPersistedVariablesStatement::class, $all);
        self::assertSame('RESET PERSIST', $all->toString());
        $statement = $binder->bind('RESET PERSIST IF EXISTS max_connections');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\ResetSettingStatement::class, $statement);
        $named = $statement->setting;
        self::assertSame(['max_connections'], $named->name);
        self::assertTrue($named->ifExists);
    }

    public function testBindHandlesLowercaseVariablesAndScopedNames(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("set @@local.sql_mode='ANSI', @x:=2, persist_only.max_connections=3");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertSame(['session','user','session'], array_map(static fn ($item) => ($item instanceof \SqlSemantics\Model\Configuration\Setting ? $item : throw new LogicException('setting'))->scope->value, $statement->settings));
        self::assertSame([['sql_mode'],['x'],['persist_only','max_connections']], array_column($statement->settings, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[1]);
        self::assertSame('2', $statement->settings[1]->value->spelling());
    }
    public function testPragmaRetainsParenthesizedValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('pragma main.cache_size(-2000);');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\AssignPragmaStatement::class, $statement);
        self::assertSame(['main', 'cache_size'], $statement->name->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Pragma\NumericArgument::class, $statement->value);
        self::assertSame(\SqlSemantics\Model\Configuration\Pragma\Sign::Negative, $statement->value->sign);
        self::assertSame('2000', $statement->value->literal->spelling());
    }

    public function testSettingDoesNotSplitATimeZoneIntervalAtTo(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SET LOCAL TIME ZONE INTERVAL '1' HOUR TO MINUTE");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertSame(['timezone'], $statement->settings[0]->name);
        self::assertSame("SET LOCAL TIME ZONE INTERVAL '1' HOUR TO MINUTE", $statement->toString());
    }

    public function testSettingRejectsAScopePrefixedSystemVariable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind('SET GLOBAL.b = 1');
    }

    public function testMakeBindsASystemVariableValueAsAnIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET sql_mode = ANSI');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier::class, $statement->settings[0]->values[0]);
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerBindWritesEachSettingForm(): array
    {
        return [
            [Dialect::MySql, null, 'SET @x = 1;', ['SET @`x` = 1', [\SqlSemantics\Model\Configuration\AssignedUserVariable::class]]],
            [Dialect::MySql, null, 'SET GLOBAL a = 1, b = 2', ['SET GLOBAL `a` = 1, GLOBAL `b` = 2', [\SqlSemantics\Model\Configuration\AssignedSetting::class, \SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::MySql, null, 'SET NAMES utf8mb4', ['SET NAMES `utf8mb4`', [\SqlSemantics\Model\Configuration\Connection\ConnectionNames::class]]],
            [Dialect::MySql, null, 'SET @a = 1 = 1', ['SET @`a` = (1 = 1)', [\SqlSemantics\Model\Configuration\AssignedUserVariable::class]]],
            [Dialect::MySql, null, 'SET PERSIST_ONLY max_connections = 1', ['SET PERSIST_ONLY `max_connections` = 1', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::MySql, null, 'SET @@PERSIST_ONLY.max_connections = 1', ['SET PERSIST_ONLY `max_connections` = 1', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::MySql, null, 'SET @@GLOBAL.max_connections = 1', ['SET GLOBAL `max_connections` = 1', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::MySql, null, 'SET sql_mode = ANSI', ['SET `sql_mode` = `ANSI`', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::PostgreSql, null, 'SET a.b.c = 1', ['SET "a"."b"."c" = 1', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::PostgreSql, null, 'SET work_mem = 1;', ['SET "work_mem" = 1', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::PostgreSql, null, 'SET search_path = a', ['SET "search_path" = "a"', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
            [Dialect::MySql, null, 'set persist_only max_connections = 1', ['SET PERSIST_ONLY `max_connections` = 1', [\SqlSemantics\Model\Configuration\AssignedSetting::class]]],
        ];
    }

    #[DataProvider('providerBindWritesEachSettingForm')]
    public function testBindWritesEachSettingForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertSame($expected, [$statement->toString(), array_map(static fn (object $setting): string => $setting::class, $statement->settings)]);
    }

    public function testPragmaBindsADatabaseSettingFromEachSpelling(): void
    {
        $binder = new \SqlSemantics\Binding\Configuration\SettingBinder();
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::Sqlite));
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::Sqlite);
        $assigned = $binder->bind($parser->parse('PRAGMA main.cache_size(5);'), $scope)[0];
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $assigned);
        self::assertSame(['main', 'cache_size'], $assigned->name);
        self::assertSame('database', $assigned->scope->value);
        self::assertSame(['5'], array_map(static fn ($value): ?string => $value->spelling(), $assigned->values));
        $equals = $binder->bind($parser->parse('PRAGMA cache_size = 7'), $scope)[0];
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $equals);
        self::assertSame(['cache_size'], $equals->name);
        self::assertSame(['7'], array_map(static fn ($value): ?string => $value->spelling(), $equals->values));
        $read = $binder->pragma(array_slice($parser->parse('PRAGMA cache_size')->tokens(), 1, 1), $parser->parse('PRAGMA cache_size'), $scope);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\ReadSetting::class, $read);
        self::assertSame(['cache_size'], $read->name);
        self::assertSame([], $binder->bind($parser->parse('SELECT 1'), $scope));
    }

    public function testScopeSeparatesTheVariableScopeFromTheName(): void
    {
        $binder = new \SqlSemantics\Binding\Configuration\SettingBinder();
        $tokens = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('SET @@GLOBAL.max_connections = 1')->tokens();
        [$rest, $scope] = $binder->scope(array_slice($tokens, 1), Dialect::MySql);
        self::assertSame('global', $scope);
        self::assertSame(['max_connections', '=', '1', ''], array_map(static fn ($token): string => $token->text, $rest));
    }

    public function testSettingSplitsTheNameAtTheAssignment(): void
    {
        $binder = new \SqlSemantics\Binding\Configuration\SettingBinder();
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SET work_mem = 1;');
        $settings = $binder->setting(array_slice($source->tokens(), 1, 3), $source, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)), 'SET');
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $settings[0]);
        self::assertSame(['work_mem'], $settings[0]->name);
        self::assertSame(['1'], array_map(static fn ($value): ?string => $value->spelling(), $settings[0]->values));
        $bound = $binder->bind($source, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql)));
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $bound[0]);
        self::assertSame(['1'], array_map(static fn ($value): ?string => $value->spelling(), $bound[0]->values));
    }
}
