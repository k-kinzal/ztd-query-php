<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration;

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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
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
final class SettingTokensTest extends TestCase
{
    public function testSplitKeepsNestedFunctionArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET @n=COALESCE(1,2), @m=3');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertCount(2, $statement->settings);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedUserVariable::class, $statement->settings[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\FunctionCall::class, $statement->settings[0]->value);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Function\DeclaredFunction::class, $statement->settings[0]->value->function);
        self::assertSame('coalesce', $statement->settings[0]->value->function->signature->name);
        self::assertCount(2, $statement->settings[0]->value->inputs());
    }
    public function testValueDoesNotResolveKeywordsAsColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET enable_seqscan TO off');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertSame('configuration-value', $statement->settings[0]->values[0]->kind->value);
        self::assertSame('OFF', $statement->settings[0]->values[0]->spelling());
    }
    public function testValueKeepsTheSignOfANumericSetting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SET work_mem = - 5');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $statement->settings[0]);
        self::assertSame('-5', $statement->settings[0]->values[0]->spelling());
        self::assertSame('SET "work_mem" = -5', $statement->toString());
    }
    public function testWordsRetainsTokenOrder(): void
    {
        $tokens = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('SET ROLE example')->tokens();
        self::assertSame(['SET','ROLE','EXAMPLE'], \SqlSemantics\Binding\Configuration\SettingTokens::words($tokens));
    }

    public function testValueBindsAnIntervalTimeZoneAsATypedInterval(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER DATABASE d SET TIME ZONE INTERVAL(2) '1'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Database\AlterDatabaseSetStatement::class, $statement);
        $setting = $statement->setting;
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\AssignedSetting::class, $setting);
        $value = $setting->values[0];
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\CastExpression::class, $value);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\IntervalStorage::class, $value->type->identity);
        self::assertSame("ALTER DATABASE \"d\" SET TIME ZONE INTERVAL(2) '1'", $statement->toString());
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerValueSpellsEverySettingForm')]
    public function testValueSpellsEverySettingForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, $statement->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerValueSpellsEverySettingForm(): iterable
    {
        return [
            'SET @n = COALESCE(1, 2), @m = 3 (MySql)' => [Dialect::MySql, null, [], 'SET @n = COALESCE(1, 2), @m = 3', 'SET @`n` = coalesce(1, 2), @`m` = 3'],
            'SET @n = JSON_ARRAY(1, JSON_ARRAY(2, 3)), @m = (1), @k = 4 (MySql)' => [Dialect::MySql, null, [], 'SET @n = JSON_ARRAY(1, JSON_ARRAY(2, 3)), @m = (1), @k = 4', 'SET @`n` = `JSON_ARRAY`(1, `JSON_ARRAY`(2, 3)), @`m` = 1, @`k` = 4'],
            'SET @a = 1, @b = f(1, (2)), @c = 3 (MySql)' => [Dialect::MySql, null, [], 'SET @a = 1, @b = f(1, (2)), @c = 3', 'SET @`a` = 1, @`b` = `f`(1, 2), @`c` = 3'],
            'SET search_path = a, b (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET search_path = a, b', 'SET "search_path" = "a", "b"'],
            'SET search_path TO "A", \'b\', c (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET search_path TO "A", \'b\', c', 'SET "search_path" = "A", \'b\', "c"'],
            'SET work_mem = -5 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET work_mem = -5', 'SET "work_mem" = -5'],
            'SET work_mem = +5 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET work_mem = +5', 'SET "work_mem" = +5'],
            'SET x = - 1.5 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = - 1.5', 'SET "x" = -1.5'],
            'SET x = 7 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = 7', 'SET "x" = 7'],
            'SET x = \'abc\' (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = \'abc\'', 'SET "x" = \'abc\''],
            'SET x TO DEFAULT (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x TO DEFAULT', 'SET "x" = DEFAULT'],
            'SET x = default (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = default', 'SET "x" = DEFAULT'],
            'SET x = on (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = on', 'SET "x" = ON'],
            'SET x = true (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = true', 'SET "x" = true'],
            'SET x = local (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = local', 'SET "x" = LOCAL'],
            'SET x = foo (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = foo', 'SET "x" = "foo"'],
            'SET x = "Foo" (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET x = "Foo"', 'SET "x" = "Foo"'],
            'SET TIME ZONE LOCAL (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET TIME ZONE LOCAL', 'SET "timezone" = LOCAL'],
            'set time zone interval \'1\' hour (PostgreSql)' => [Dialect::PostgreSql, null, [], 'set time zone interval \'1\' hour', 'SET TIME ZONE INTERVAL \'1\' HOUR'],
            'SET TIME ZONE \'UTC\' (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET TIME ZONE \'UTC\'', 'SET "timezone" = \'UTC\''],
            'SET TIME ZONE -7 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'SET TIME ZONE -7', 'SET "timezone" = -7'],
            'ALTER SYSTEM SET x = a, b (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER SYSTEM SET x = a, b', 'ALTER SYSTEM SET "x" = "a", "b"'],
            'ALTER ROLE r SET x = 1, 2 (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER ROLE r SET x = 1, 2', 'ALTER ROLE "r" SET "x" = 1, 2'],
            'ALTER DATABASE d SET x FROM CURRENT (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER DATABASE d SET x FROM CURRENT', 'ALTER DATABASE "d" SET "x" FROM CURRENT'],
            'ALTER FUNCTION f() SET x = \'a\', \'b\' (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER FUNCTION f() SET x = \'a\', \'b\'', 'ALTER FUNCTION "f"() SET "x" = \'a\', \'b\''],
            'SET SESSION sql_mode = \'ANSI\', @x = 1 (MySql)' => [Dialect::MySql, null, [], 'SET SESSION sql_mode = \'ANSI\', @x = 1', 'SET `sql_mode` = \'ANSI\', @`x` = 1'],
            'SET GLOBAL max_connections = DEFAULT (MySql)' => [Dialect::MySql, null, [], 'SET GLOBAL max_connections = DEFAULT', 'SET GLOBAL `max_connections` = DEFAULT'],
            'SET sql_mode = default (MySql)' => [Dialect::MySql, null, [], 'SET sql_mode = default', 'SET `sql_mode` = DEFAULT'],
            'SET sql_mode = on (MySql)' => [Dialect::MySql, null, [], 'SET sql_mode = on', 'SET `sql_mode` = ON'],
            'SET sql_mode = ansi (MySql)' => [Dialect::MySql, null, [], 'SET sql_mode = ansi', 'SET `sql_mode` = `ansi`'],
            'SET autocommit = -1 (MySql)' => [Dialect::MySql, null, [], 'SET autocommit = -1', 'SET `autocommit` = (- 1)'],
            'SET autocommit = + 1 (MySql)' => [Dialect::MySql, null, [], 'SET autocommit = + 1', 'SET `autocommit` = (+ 1)'],
        ];
    }
}
