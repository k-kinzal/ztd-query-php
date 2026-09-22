<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Query\RelationFactory::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintGroups::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[CoversClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(Binder::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryContext::class)]
#[UsesClass(\SqlSemantics\Binding\Query\QueryNodes::class)]
#[UsesClass(\SqlSemantics\Binding\Query\SqliteLists::class)]
#[UsesClass(\SqlSemantics\Binding\Query\UsingJoin::class)]
#[CoversClass(\SqlSemantics\Binding\Scalar\FunctionRules::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\ScalarBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\SchemaEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\TableAlteration::class)]
#[CoversClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\MutationBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\StatementBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\UtilityBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Statement\ValuesBinder::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Binding\TypeResolution::class)]
#[UsesClass(Dialect::class)]
#[UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\ConstraintKind::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(\SqlSemantics\Schema::class)]
#[UsesClass(SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\Nullability::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
#[CoversClass(\SqlSemantics\Binding\Query\QueryRelation::class)]
#[UsesClass(\SqlSemantics\Binding\Analysis\Diagnostics::class)]
#[UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\IndirectionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\ConflictBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentRules::class)]
#[UsesClass(\SqlSemantics\Binding\Write\InsertionBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Write\AssignmentBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\TransactionSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingBinder::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SpecialSettings::class)]
#[UsesClass(\SqlSemantics\Binding\Configuration\SettingTokens::class)]
#[UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[UsesClass(\SqlSemantics\Model\Validation\InvalidStructure::class)]
#[UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\DefinitionBinder::class)]
#[UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[UsesClass(\SqlSemantics\Binding\Write\MergeBinder::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\ReferenceReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
#[UsesClass(\SqlSemantics\Ast\Definition\IndexKeys::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionMatch::class)]
#[UsesClass(\SqlSemantics\Binding\Scalar\FunctionResolver::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexEvolution::class)]
#[UsesClass(\SqlSemantics\Binding\Schema\IndexBinder::class)]
#[UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\Builtins::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\BuiltinResult::class)]
#[UsesClass(\SqlSemantics\Schema\Functions\SignatureInvariant::class)]
#[UsesClass(\SqlSemantics\Schema\IndexDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\IndexElement::class)]
#[UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[UsesClass(\SqlSemantics\Serializer::class)]
#[UsesClass(\SqlSemantics\StatementFactory::class)]
#[UsesClass(\SqlSemantics\SimpleSerializer::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
#[UsesClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[UsesClass(\SqlSemantics\Schema\ReferentialAction::class)]
#[UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\SourceEdit::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[UsesClass(\SqlSemantics\Model\Transformation\TreeEdit::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CommandStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\RelationQuery::class)]
#[UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Literal::class)]
#[UsesClass(\SqlSemantics\Model\Sql\ExpressionFactory::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Build::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Parts::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Atom::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Tree::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Source::class)]
#[UsesClass(\SqlSemantics\Model\Sql\Format::class)]
final class RelationFactoryTest extends TestCase
{
    public function testFunctionExposesTypedTableFunctionColumns(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT n FROM generate_series(1, 3) AS g(n)');
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
        self::assertSame('GENERATE_SERIES', $query->relations[0]->query?->outputs[0]->expression->symbol);
    }

    public function testAliasHidesJoinInputs(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a, q.b FROM (t a JOIN t b ON a.id = b.id) AS q(a,b)');
        self::assertSame(['a', 'b'], array_column($query->outputs, 'name'));
        self::assertSame('q', $query->relations[0]->alias);
        self::assertCount(2, $query->relations[0]->query->relations ?? []);
    }

    public function testAliasesPreservesQuotedColumnLabels(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT g."Value" FROM generate_series(1,2) AS g("Value")');
        self::assertSame('Value', $query->outputs[0]->name);
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['json_each'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['json_tree'])]
    public function testFunctionBindsJsonTableColumnsAndArguments(string $name): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT * FROM ' . $name . "('[1,2]') AS items");
        self::assertSame(['key','value','type','atom','id','parent','fullkey','path'], array_column($query->outputs, 'name'));
        self::assertSame(['dynamic','dynamic','text','dynamic','integer','integer','text','text'], array_map(static fn ($output): string => $output->expression->type->name, $query->outputs));
        self::assertSame('items', $query->relations[0]->alias);
        self::assertNotNull($query->relations[0]->query);
        self::assertSame(strtoupper($name), $query->relations[0]->query->outputs[0]->expression->symbol);
        self::assertSame("'[1,2]'", $query->relations[0]->query->outputs[0]->expression->operands[0]->symbol);
    }

    public function testFunctionKeepsTheImplicitFunctionName(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM generate_series(1, 2)');
        self::assertSame(['generate_series'], array_column($query->outputs, 'name'));
        self::assertSame('integer', $query->outputs[0]->expression->type->name);
    }

    public function testRescopeAssignsAliasedJoinInputsToTheirInnerScope(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id INTEGER)');
        $query = (new Binder($schema))->bind('SELECT q.a, q.b FROM (t a JOIN t b ON a.id=b.id) AS q(a,b)');
        $inner = $query->relations[0]->query;
        self::assertNotNull($inner);
        self::assertNotSame($query->scopeId, $inner->scopeId);
        self::assertSame([$inner->scopeId, $inner->scopeId], array_column($inner->relations, 'scopeId'));
        self::assertInstanceOf(\SqlSemantics\Model\Join::class, $inner->from);
        self::assertSame($inner->relations[0], $inner->from->left);
        self::assertSame($inner->relations[1], $inner->from->right);
        self::assertSame($inner->relations[0]->id, $inner->outputs[0]->expression->binding?->relationId);
        self::assertSame($inner->relations[1]->id, $inner->from->condition?->operands[1]->binding?->relationId);
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql, 'integer'])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql, 'int unsigned'])]
    public function testTableColumnsPreservesJsonTableSchemaAndInputDependencies(Dialect $dialect, string $ordinalType): void
    {
        $schema = (new SchemaBuilder($dialect))->build('CREATE TABLE t (data JSON)');
        $query = (new Binder($schema))->bind("SELECT j.n, j.label FROM t, JSON_TABLE (t.data, '$[*]' COLUMNS (n FOR ORDINALITY, label VARCHAR(50) PATH '$.name')) AS j");
        self::assertSame(['n', 'label'], array_column($query->outputs, 'name'));
        self::assertSame([$ordinalType, 'varchar'], array_map(static fn ($output): string => $output->expression->type->name, $query->outputs));
        self::assertSame(['50'], $query->outputs[1]->expression->type->modifiers);
        self::assertSame('not-null', $query->outputs[0]->expression->nullability->value);
        self::assertSame('maybe-null', $query->outputs[1]->expression->nullability->value);
        self::assertSame(['label', 'data'], array_map(static fn ($binding): string => $binding->column->name, $query->outputs[1]->expression->lineage()));
    }

    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::PostgreSql])]
    #[\PHPUnit\Framework\Attributes\TestWith([Dialect::MySql])]
    public function testTableColumnsPreservesNestedJsonColumnsAndTheirNullableRows(Dialect $dialect): void
    {
        $query = (new Binder((new SchemaBuilder($dialect))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, NESTED PATH '$.children[*]' COLUMNS (child FOR ORDINALITY, value INTEGER PATH '$'))) AS j");
        self::assertSame(['n', 'child', 'value'], array_column($query->outputs, 'name'));
        self::assertSame('maybe-null', $query->outputs[1]->expression->nullability->value);
        self::assertSame('integer', $query->outputs[2]->expression->type->name);
    }

    public function testTableColumnsReadsXmlTableDeclarations(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT x.n, x.value FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id') AS x");
        self::assertSame(['n', 'value'], array_column($query->outputs, 'name'));
        self::assertSame(['integer', 'integer'], array_map(static fn ($output): string => $output->expression->type->name, $query->outputs));
        self::assertSame('XMLTABLE', $query->relations[0]->query?->outputs[1]->expression->symbol);
    }

}
