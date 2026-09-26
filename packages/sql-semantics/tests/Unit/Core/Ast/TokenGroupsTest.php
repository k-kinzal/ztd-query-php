<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;

#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Binding\ExpressionBinder::class)]
#[UsesClass(\SqlSemantics\Core\Binding\ExpressionRules::class)]
#[UsesClass(\SqlSemantics\Core\Binding\FromBinder::class)]
#[UsesClass(\SqlSemantics\Core\Binding\LiteralBinder::class)]
#[UsesClass(\SqlSemantics\Core\Binding\NullFacts::class)]
#[UsesClass(\SqlSemantics\Core\Binding\ProjectionBinder::class)]
#[UsesClass(\SqlSemantics\Core\Binding\SelectBinder::class)]
#[UsesClass(\SqlSemantics\Core\Binding\SyntaxGuard::class)]
#[UsesClass(\SqlSemantics\Core\Binding\SelectModifiersBinder::class)]
#[UsesClass(\SqlSemantics\Core\Binding\TypeResolution::class)]
#[UsesClass(\SqlSemantics\Core\Binder::class)]
#[UsesClass(\SqlSemantics\Core\SchemaBuilder::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\StatementList::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Core\Binding\BoundRelation::class)]
#[UsesClass(\SqlSemantics\Core\Binding\IdentitySequence::class)]
#[UsesClass(\SqlSemantics\Core\Binding\Scope::class)]
#[UsesClass(\SqlSemantics\Core\Binding\TableResolver::class)]
#[UsesClass(\SqlSemantics\Core\Model\ColumnBinding::class)]
#[UsesClass(\SqlSemantics\Core\Model\Expression::class)]
#[UsesClass(\SqlSemantics\Core\Model\Join::class)]
#[UsesClass(\SqlSemantics\Core\Model\Ordering::class)]
#[UsesClass(\SqlSemantics\Core\Model\OutputColumn::class)]
#[UsesClass(\SqlSemantics\Core\Model\BoundSelect::class)]
#[UsesClass(\SqlSemantics\Core\Model\TableUse::class)]
#[UsesClass(\SqlSemantics\Core\Schema::class)]
#[UsesClass(\SqlSemantics\Core\Schema\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Core\Schema\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Core\Schema\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Core\Type\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\QueryRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\QueryRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\QueryRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\SchemaRules::class)]
#[Medium]
final class TokenGroupsTest extends TestCase
{
    public function testParenthesesRetainsNestedGroups(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT COALESCE((1), 2), (3)');
        $groups = \SqlSemantics\Core\Ast\TokenGroups::parentheses($tree->tokens());
        self::assertCount(2, $groups);
        self::assertSame(['(', '1', ')', ',', '2'], array_column($groups[0], 'text'));
        self::assertSame(['3'], array_column($groups[1], 'text'));
    }

    public function testNamesPreservesQuotedCommas(): void
    {
        $node = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('CREATE TABLE users ("a,b" INTEGER, c INTEGER, PRIMARY KEY ("a,b", c))')->find('columnList')[0];
        $names = \SqlSemantics\Core\Ast\TokenGroups::names($node->tokens(), new \SqlSemantics\Core\Ast\Identifiers(PostgreSqlDialect::PostgreSql));
        self::assertSame(['a,b', 'c'], $names);
    }
}
