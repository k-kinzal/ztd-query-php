<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;

#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
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
#[UsesClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
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
final class TreeTest extends TestCase
{
    public function testOuterStopsAtTheRequestedGrammarBoundary(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT 1+2');
        $nodes = \SqlSemantics\Core\Ast\Tree::outer($tree, ['a_expr']);
        self::assertCount(1, $nodes);
        self::assertSame('1 + 2', \SqlSemantics\Core\Ast\Tree::text($nodes[0]));
        self::assertCount(3, $tree->find('a_expr'));
    }

    public function testChildDoesNotSearchNestedScopes(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT id FROM users');
        self::assertNull(\SqlSemantics\Core\Ast\Tree::child($tree, ['columnref']));
        self::assertNotNull(\SqlSemantics\Core\Ast\Tree::child($tree->find('c_expr')[0], ['columnref']));
    }

    public function testSignificantRemovesEmptyProductions(): void
    {
        $token = new \SqlParser\Lexer\Token(1, 'ICONST', '1', 7);
        $node = new \SqlParser\Parser\Node('expr', 0, [new \SqlParser\Parser\Node('empty', 0, []), $token]);
        self::assertSame([$token], \SqlSemantics\Core\Ast\Tree::significant($node));
    }

    public function testTextKeepsTerminalSpellings(): void
    {
        $token = new \SqlParser\Lexer\Token(1, 'SCONST', "'a b'", 0);
        self::assertSame("'a b'", \SqlSemantics\Core\Ast\Tree::text($token));
    }

    public function testUnsupportedCarriesOriginalSyntax(): void
    {
        $node = new \SqlParser\Parser\Node('expr', 0, []);
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Unsupported custom operation');
        \SqlSemantics\Core\Ast\Tree::unsupported($node, 'custom operation');
    }

    public function testAssertChildrenRejectsUnknownClauses(): void
    {
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('SELECT id FROM users');
        $this->expectException(SemanticException::class);
        \SqlSemantics\Core\Ast\Tree::assertChildren($tree->find('simple_select')[0], [], ['SELECT']);
    }
}
