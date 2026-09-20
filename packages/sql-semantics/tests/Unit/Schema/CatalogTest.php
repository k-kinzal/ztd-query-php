<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;
use Tests\Scenario\AnalysisCase;

#[CoversClass(\SqlSemantics\Schema\Catalog::class)]
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
#[CoversClass(\SqlSemantics\Schema\ColumnDefinition::class)]
#[CoversClass(\SqlSemantics\Schema\TableConstraint::class)]
#[CoversClass(\SqlSemantics\Schema\TableDefinition::class)]
#[CoversClass(SemanticException::class)]
#[CoversClass(\SqlSemantics\Type\TypeDescriptor::class)]
#[Medium]
final class CatalogTest extends TestCase
{
    public function testRetainsTheDialectAndAllDeclarations(): void
    {
        $case = new AnalysisCase();
        $catalog = $case->analyzer->schema($case->parser->parse('CREATE TABLE a (id INTEGER); CREATE TABLE b (id INTEGER)'));
        self::assertSame(Dialect::PostgreSql, $catalog->dialect);
        self::assertSame(['a', 'b'], array_column($catalog->tables, 'name'));
    }

}
