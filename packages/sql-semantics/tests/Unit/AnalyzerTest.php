<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Analyzer::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[CoversClass(\SqlSemantics\Analysis\FromReader::class)]
#[CoversClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[CoversClass(\SqlSemantics\Analysis\NullFacts::class)]
#[CoversClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SelectReader::class)]
#[CoversClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[CoversClass(\SqlSemantics\Analysis\TailReader::class)]
#[CoversClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
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
#[CoversClass(\SqlSemantics\Model\SelectQuery::class)]
#[CoversClass(\SqlSemantics\Model\TableUse::class)]
#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class AnalyzerTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testAnalyzeSelfJoinPreservesOccurrenceIdentityAndNullProvenance(Dialect $dialect): void
    {
        $case = new AnalysisCase($dialect);
        $query = $case->query('SELECT child.id, parent.score AS parent_score, COALESCE(parent.score, 0) AS effective_score FROM users AS child LEFT JOIN users AS parent ON child.parent_id = parent.id');
        self::assertSame('s0', $query->scopeId);
        self::assertSame(['r0', 'r1'], array_column($query->relations, 'id'));
        self::assertSame($query->relations[0]->declaration, $query->relations[1]->declaration);
        self::assertSame(['id', 'parent_score', 'effective_score'], array_column($query->outputs, 'name'));
        self::assertSame(Nullability::NotNull, $query->outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $query->outputs[1]->expression->nullability);
        self::assertSame(['j0'], $query->outputs[1]->expression->nullExtendedBy);
        self::assertSame('integer', $query->outputs[1]->expression->type->name);
        self::assertSame(Nullability::NotNull, $query->outputs[2]->expression->nullability);
        self::assertSame([], $query->outputs[2]->expression->nullExtendedBy);
        self::assertSame(['j0'], $query->outputs[2]->expression->operands[0]->nullExtendedBy);
        self::assertSame('r1', $query->outputs[2]->expression->lineage()[0]->relationId);
        self::assertSame(Nullability::NotNull, $query->relations[1]->declaration->columns[2]->nullability);
    }
    public function testDoesNotMutateOrReparseTheInputTree(): void
    {
        $case = new AnalysisCase();
        $tree = $case->parser->parse('/* source */ SELECT score FROM users');
        $query = $case->analyzer->analyze($tree, $case->schema());
        self::assertSame($tree, $query->source);
        self::assertSame($tree->find('columnref')[0], $query->outputs[0]->expression->source);
        self::assertSame('/* source */ SELECT score FROM users', $tree->toString());
    }
    public function testRejectsCatalogDialectMismatch(): void
    {
        $case = new AnalysisCase();
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('catalog and query dialects differ');
        (new \SqlSemantics\Analyzer(Dialect::Sqlite))->analyze((new \SqlParser\Sqlite\SqliteParser())->parse('SELECT 1'), $case->schema());
    }
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testKeepsIdsLocalToEachAnalysis(Dialect $dialect): void
    {
        $case = new AnalysisCase($dialect);
        $case->query('SELECT a.id FROM users a LEFT JOIN users b ON a.id=b.id');
        self::assertSame('r0', $case->query('SELECT id FROM users')->relations[0]->id);
    }


    public function testSchemaAcceptsSeparateDeclarationTrees(): void
    {
        $case = new AnalysisCase();
        $catalog = $case->analyzer->schema($case->parser->parse('CREATE TABLE a (id INTEGER)'), $case->parser->parse('CREATE TABLE b (id INTEGER)'));
        self::assertSame(['a', 'b'], array_column($catalog->tables, 'name'));
    }
}
