<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Platform\Sqlite\IdentifierReader;
use SqlSemantics\Statement\Connection\AttachDatabase;
use SqlSemantics\Statement\Connection\DetachDatabase;
use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Literal\StringLiteral;
use SqlSemantics\Statement\Maintenance\Vacuum;
use SqlSemantics\Statement\Maintenance\VacuumInto;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Structures database file requests and their expression scopes without opening any database.
 * @visibility SqlSemantics
 */
final class DatabaseReader
{
    /**
     * Distinguishes rebuilding in place from creating a compact copy.
     */
    public function vacuum(Node $source, Catalog $catalog): Vacuum|VacuumInto
    {
        Tree::assertChildren($source, ['nm', 'vinto'], ['VACUUM']);
        $name = Tree::child($source, ['nm']);
        $schema = $name === null ? new Name('main') : (new IdentifierReader())->name($name);
        $into = Tree::child($source, ['vinto']);
        if ($into === null) {
            return new Vacuum($schema);
        }
        $destination = Tree::child($into, ['expr']);
        assert($destination !== null, 'INTO owns a destination expression.');
        $scope = new Scope($catalog);
        return new VacuumInto($scope, (new ExpressionReader())->read($destination, $scope), $schema);
    }

    /**
     * Bare identifier operands denote text only at each attachment operand's root.
     */
    public function attachment(Node $source, Catalog $catalog): AttachDatabase|DetachDatabase
    {
        Tree::assertChildren($source, ['database_kw_opt', 'expr', 'key_opt'], ['ATTACH', 'DETACH', 'AS']);
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'expr'));
        $scope = new Scope($catalog);
        if (\SqlSemantics\Statement\Identifier\Ascii::upper($source->tokens()[0]->text) === 'DETACH') {
            assert(count($operands) === 1, 'DETACH owns one target expression.');
            return new DetachDatabase($scope, $this->attachmentExpression($operands[0], $scope));
        }
        assert(count($operands) === 2, 'ATTACH owns a file and schema expression.');
        $key = Tree::child($source, ['key_opt']);
        $keyExpression = $key === null ? null : Tree::child($key, ['expr']);
        return new AttachDatabase($scope, $this->attachmentExpression($operands[0], $scope), $this->attachmentExpression($operands[1], $scope), $keyExpression === null ? null : $this->attachmentExpression($keyExpression, $scope));
    }

    /**
     * Mirrors SQLite's root-only identifier-to-text rule, including redundant parentheses.
     */
    public function attachmentExpression(Node $source, Scope $scope): ScalarExpression
    {
        $children = Tree::significant($source);
        if (count($children) === 3 && $children[0] instanceof Token && $children[0]->text === '(' && $children[1] instanceof Node && $children[1]->name === 'expr') {
            return $this->attachmentExpression($children[1], $scope);
        }
        if (count($children) === 1 && $children[0] instanceof Token && in_array($children[0]->name, ['ID', 'INDEXED', 'JOIN_KW'], true)) {
            return new SqliteText(new StringLiteral((new IdentifierReader())->name($children[0])->value));
        }
        return (new ExpressionReader())->read($source, $scope);
    }
}
