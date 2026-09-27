<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;

#[CoversClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\SchemaRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\NameRules::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\SchemaRules::class)]
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
