<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use SqlSemantics\Type\Nullability;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Ast\SchemaReader::class)]
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
#[CoversClass(\SqlSemantics\Analyzer::class)]
#[CoversClass(\SqlSemantics\Ast\ColumnReader::class)]
#[CoversClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[CoversClass(\SqlSemantics\Ast\Identifiers::class)]
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
final class SchemaReaderTest extends TestCase
{
    #[DataProviderExternal(AnalysisCase::class, 'providerLanguages')]
    public function testReadDeclaredKeysDefaultsAndChecks(Dialect $dialect): void
    {
        $schema = (new AnalysisCase($dialect))->schema('CREATE TABLE users (id INTEGER PRIMARY KEY, parent_id INTEGER, score INTEGER NOT NULL DEFAULT 1, UNIQUE(score), FOREIGN KEY (parent_id) REFERENCES users(id), CHECK (score > 0))');
        $table = $schema->tables[0];
        self::assertSame(['id', 'parent_id', 'score'], array_column($table->columns, 'name'));
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
        self::assertNotNull($table->columns[2]->defaultExpression);
        self::assertSame(4, count($table->constraints));
        self::assertSame(['parent_id'], $table->constraints[2]->columns);
        self::assertSame(['users'], $table->constraints[2]->referencedTable);
        self::assertSame(['id'], $table->constraints[2]->referencedColumns);
        self::assertNotNull($table->constraints[3]->expression);
    }
    public function testTableRejectsDuplicateDeclarations(): void
    {
        $case = new AnalysisCase();
        $tree = $case->parser->parse('CREATE TABLE users (id INTEGER)');
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Duplicate table');
        $case->analyzer->schema($tree, $tree);
    }
    public function testPrimaryKeysRejectsMissingColumn(): void
    {
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('unknown column');
        (new AnalysisCase())->schema('CREATE TABLE users (id INTEGER, PRIMARY KEY (missing))');
    }


    public function testColumnNodesPreservesSqliteDeclarationOrder(): void
    {
        $table = (new AnalysisCase(Dialect::Sqlite))->schema('CREATE TABLE users (z INTEGER, a TEXT, m REAL)')->tables[0];
        self::assertSame(['z', 'a', 'm'], array_column($table->columns, 'name'));
    }

    public function testPrimaryNotNullRespectsSqliteDescException(): void
    {
        $table = (new AnalysisCase(Dialect::Sqlite))->schema('CREATE TABLE users (id INTEGER PRIMARY KEY DESC)')->tables[0];
        self::assertSame(Nullability::MaybeNull, $table->columns[0]->nullability);
    }

    public function testValidateRejectsCreateAsSelect(): void
    {
        $this->expectException(SemanticException::class);
        (new AnalysisCase(Dialect::Sqlite))->schema('CREATE TABLE users AS SELECT 1 AS id');
    }

    public function testPrimaryKeysResolvesCaseInsensitiveSqliteConstraintColumns(): void
    {
        $table = (new AnalysisCase(Dialect::Sqlite))->schema('CREATE TABLE users (id INTEGER, PRIMARY KEY (ID))')->tables[0];
        self::assertSame(Nullability::NotNull, $table->columns[0]->nullability);
    }
}
