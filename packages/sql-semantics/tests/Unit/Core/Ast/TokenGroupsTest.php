<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Platform\PostgreSql\Dialect as PostgreSqlDialect;
use Tests\Contract\Resolved;

#[CoversClass(\SqlSemantics\Core\Ast\TokenGroups::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ColumnReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\ConstraintReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Identifiers::class)]
#[UsesClass(\SqlSemantics\Core\Ast\SchemaReader::class)]
#[UsesClass(\SqlSemantics\Core\Ast\Tree::class)]
#[UsesClass(\SqlSemantics\Core\Ast\TypeReader::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\ColumnDefinition::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableConstraint::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TableDefinition::class)]
#[UsesClass(SemanticException::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Core\Policy\SyntaxRules::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeRules::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Builtin::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeName::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\TypeDeclaration::class)]
#[CoversClass(\SqlSemantics\Core\Ast\Numbers::class)]
#[CoversClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\TypeReader::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
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
    public function testKeyNamesSeparatesPrefixLengthsAndSortDirections(): void
    {
        $state = Resolved::of((new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (id INT, label VARCHAR(30), UNIQUE KEY idx (label(10) DESC, id ASC))', []));
        self::assertSame(['label', 'id'], $state->declarations[0]->constraints[0]->columns);
    }
    public function testConstraintHeaderSeparatesQuotedNames(): void
    {
        $node = (new \SqlParser\PostgreSql\PostgreSqlParser())->parse('CREATE TABLE t (id INTEGER, CONSTRAINT "primary" PRIMARY KEY (id))')->find('TableConstraint')[0];
        [$name, $tokens] = \SqlSemantics\Core\Ast\TokenGroups::constraintHeader($node->tokens(), new \SqlSemantics\Core\Ast\Identifiers(PostgreSqlDialect::PostgreSql));
        self::assertSame('primary', $name);
        self::assertSame(['PRIMARY', 'KEY', '(', 'id', ')'], array_column($tokens, 'text'));
    }

}
