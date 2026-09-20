<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Analysis\ExpressionReader::class)]
#[UsesClass(\SqlSemantics\Analysis\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Analysis\FromReader::class)]
#[UsesClass(\SqlSemantics\Analysis\LiteralReader::class)]
#[UsesClass(\SqlSemantics\Analysis\NullFacts::class)]
#[UsesClass(\SqlSemantics\Analysis\ProjectionReader::class)]
#[UsesClass(\SqlSemantics\Analysis\SelectReader::class)]
#[UsesClass(\SqlSemantics\Analysis\SyntaxGuard::class)]
#[UsesClass(\SqlSemantics\Analysis\TailReader::class)]
#[UsesClass(\SqlSemantics\Analysis\TypeResolution::class)]
#[UsesClass(\SqlSemantics\Analyzer::class)]
#[UsesClass(\SqlSemantics\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Model\Join::class)]
#[UsesClass(\SqlSemantics\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Model\SelectQuery::class)]
#[UsesClass(\SqlSemantics\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Schema\Catalog::class)]
#[UsesClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Schema\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class StatementListTest extends TestCase
{
    public function testReadPreservesStatementBoundaries(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT 1; SELECT 2;');
        $statements = \SqlSemantics\Ast\StatementList::read($tree, Dialect::PostgreSql);
        self::assertCount(2, $statements);
        self::assertSame('SELECT 2', \SqlSemantics\Ast\Tree::text($statements[1]));
    }
    public function testRejectsAnotherParsersRoot(): void
    {
        $tree = (new \SqlParser\Sqlite\SqliteParser())->parse('SELECT 1');
        $this->expectException(SemanticException::class);
        \SqlSemantics\Ast\StatementList::read($tree, Dialect::PostgreSql);
    }

}
