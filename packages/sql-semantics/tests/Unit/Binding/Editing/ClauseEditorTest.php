<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Editing;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Sql;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Binding\Editing\ClauseEditor::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Serializer::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SemanticException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaBuilder::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\StatementFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\SimpleSerializer::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\StatementContext::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Binding\Editing\ValueList::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Schema\FunctionSignature::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\Nullability::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Expression::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Join::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Diagnostic::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ExpressionKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundSelect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\TableUse::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Ordering::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(OutputColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\JoinKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\BoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\MergeAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Destination::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Insertion::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Merge::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\Assignment::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Write\ConflictAction::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Configuration\Setting::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\SourceEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\Context::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Transformation\TreeEdit::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CommandStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\InsertStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ConfigurationStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\TableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\DeleteStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\MergeStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\RelationQuery::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\ValuesStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\UpdateStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CompoundStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateIndexStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Statement\CreateTableStatement::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\IndexDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Definition\TableDeclaration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Traversal\Expressions::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\ExpressionInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(InvalidStructure::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\StatementInvariant::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Model\Validation\Collections::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Literal::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\ExpressionFactory::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Build::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Parts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Atom::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Tree::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Source::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Sql\Format::class)]
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
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\OptionReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlSemantics\Ast\Definition\IndexReader::class)]
final class ClauseEditorTest extends TestCase
{
    public function testReplaceChangesOnlyTheOuterPredicate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT (SELECT 1 WHERE TRUE) WHERE TRUE');
        $tree = \SqlSemantics\Binding\Editing\ClauseEditor::replace($statement, 'where', Sql\Parts::expressions('WHERE', [Expression::literal(false, Dialect::PostgreSql)]));
        $changed = $binder->bind($tree->toString());
        self::assertSame('FALSE', $changed->where?->symbol);
        self::assertSame('TRUE', $changed->outputs[0]->expression->query?->where?->symbol);
    }
    public function testNamesRejectsAnUnknownComponentRole(): void
    {
        $this->expectException(InvalidStructure::class);
        \SqlSemantics\Binding\Editing\ClauseEditor::names('invalid');
    }
    public function testCombinedReturningKeepsItsSiblingPredicate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('DELETE FROM t WHERE id=1 RETURNING id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\DeleteStatement::class, $statement);
        $changed = $statement->withWhere(Expression::literal(true, Dialect::Sqlite));
        self::assertSame('TRUE', $changed->where?->symbol);
        self::assertSame('id', $changed->outputs[0]->expression->binding?->column->name);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerClauseChanges')]
    public function testReplacePreservesSiblingClauses(Dialect $dialect, string $sql, string $role, string $replacement, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER,n INTEGER)'));
        $statement = $binder->bind($sql);
        $tree = \SqlSemantics\Binding\Editing\ClauseEditor::replace($statement, $role, new Sql\Tree('clause', [new Sql\Atom('syntax', $replacement)]));
        self::assertSame($expected, $binder->bind($tree->toString())->toString());
        self::assertSame($sql, $statement->source->toString());
    }

    /**
     * @return list<array{Dialect,string,string,string,string}>
     */
    public static function providerClauseChanges(): array
    {
        return [
            [Dialect::PostgreSql,'SELECT id FROM t','outputs','n AS next','SELECT n AS next FROM t'],
            [Dialect::MySql,'SELECT id FROM t','outputs','n AS next','SELECT n AS next FROM t'],
            [Dialect::Sqlite,'SELECT id FROM t','outputs','n AS next','SELECT n AS next FROM t'],
            [Dialect::PostgreSql,'SELECT 1','from','FROM t','SELECT 1 FROM t'],
            [Dialect::MySql,'SELECT 1','from','FROM t','SELECT 1 FROM t'],
            [Dialect::Sqlite,'SELECT 1','from','FROM t','SELECT 1 FROM t'],
            [Dialect::PostgreSql,'SELECT id FROM t','groupBy','GROUP BY id','SELECT id FROM t GROUP BY id'],
            [Dialect::MySql,'SELECT id FROM t','groupBy','GROUP BY id','SELECT id FROM t GROUP BY id'],
            [Dialect::Sqlite,'SELECT id FROM t','groupBy','GROUP BY id','SELECT id FROM t GROUP BY id'],
            [Dialect::PostgreSql,'SELECT id FROM t GROUP BY id','having','HAVING id>1','SELECT id FROM t GROUP BY id HAVING id > 1'],
            [Dialect::MySql,'SELECT id FROM t GROUP BY id','having','HAVING id>1','SELECT id FROM t GROUP BY id HAVING id > 1'],
            [Dialect::Sqlite,'SELECT id FROM t GROUP BY id','having','HAVING id>1','SELECT id FROM t GROUP BY id HAVING id > 1'],
            [Dialect::PostgreSql,'SELECT id FROM t','orderBy','ORDER BY id DESC','SELECT id FROM t ORDER BY id DESC'],
            [Dialect::PostgreSql,'SELECT id FROM t ORDER BY id LIMIT 1','orderBy','ORDER BY n','SELECT id FROM t ORDER BY n LIMIT 1'],
            [Dialect::MySql,'SELECT id FROM t','orderBy','ORDER BY id DESC','SELECT id FROM t ORDER BY id DESC'],
            [Dialect::Sqlite,'SELECT id FROM t','orderBy','ORDER BY id DESC','SELECT id FROM t ORDER BY id DESC'],
            [Dialect::PostgreSql,'TABLE t','table','t','TABLE t'],
            [Dialect::MySql,'TABLE t','table','t','TABLE t'],
            [Dialect::PostgreSql,'INSERT INTO t VALUES(1,2)','rows','VALUES(3,4)','INSERT INTO t VALUES (3, 4)'],
            [Dialect::MySql,'INSERT INTO t VALUES(1,2)','rows','VALUES(3,4)','INSERT INTO t VALUES (3, 4)'],
            [Dialect::Sqlite,'INSERT INTO t VALUES(1,2)','rows','VALUES(3,4)','INSERT INTO t VALUES (3, 4)'],
            [Dialect::PostgreSql,'UPDATE t SET n=1 WHERE id=2','writes','id=3','UPDATE t SET id = 3 WHERE id = 2'],
            [Dialect::MySql,'UPDATE t SET n=1 WHERE id=2','writes','id=3','UPDATE t SET id = 3 WHERE id = 2'],
            [Dialect::Sqlite,'UPDATE t SET n=1 WHERE id=2','writes','id=3','UPDATE t SET id = 3 WHERE id = 2'],
            [Dialect::PostgreSql,'INSERT INTO t VALUES(1,2) RETURNING id','returning','RETURNING n','INSERT INTO t VALUES (1, 2) RETURNING n'],
            [Dialect::Sqlite,'INSERT INTO t VALUES(1,2) RETURNING id','returning','RETURNING n','INSERT INTO t VALUES (1, 2) RETURNING n'],
            [Dialect::Sqlite,'UPDATE t SET n=1 WHERE id=2 RETURNING id','returning','RETURNING n','UPDATE t SET n = 1 WHERE id = 2 RETURNING n'],
            [Dialect::Sqlite,'UPDATE t SET n=1 WHERE id=2','returning','RETURNING n','UPDATE t SET n = 1 WHERE id = 2 RETURNING n'],
            [Dialect::Sqlite,'DELETE FROM t RETURNING id','where','WHERE id=2','DELETE FROM t WHERE id = 2 RETURNING id'],
        ];
    }
    public function testReplaceRejectsAClauseAbsentFromTheStatementGrammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('BEGIN');
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('The statement has no structural position for where.');
        \SqlSemantics\Binding\Editing\ClauseEditor::replace($statement, 'where', new Sql\Tree('where', []));
    }
}
