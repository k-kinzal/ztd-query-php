<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Ast\TokenGroups::class)]
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
#[UsesClass(\SqlSemantics\Ast\StatementList::class)]
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
final class TokenGroupsTest extends TestCase
{
    public function testParenthesesRetainsNestedGroups(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT COALESCE((1), 2), (3)');
        $groups = \SqlSemantics\Ast\TokenGroups::parentheses($tree->tokens());
        self::assertCount(2, $groups);
        self::assertSame(['(', '1', ')', ',', '2'], array_column($groups[0], 'text'));
        self::assertSame(['3'], array_column($groups[1], 'text'));
    }


    public function testNamesPreservesQuotedCommas(): void
    {
        $node = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('CREATE TABLE users ("a,b" INTEGER, c INTEGER, PRIMARY KEY ("a,b", c))')->find('columnList')[0];
        $names = \SqlSemantics\Ast\TokenGroups::names($node->tokens(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        self::assertSame(['a,b', 'c'], $names);
    }
}
