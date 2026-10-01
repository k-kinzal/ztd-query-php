<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Identifier\Quote;

/**
 * Decodes identifier values and namespace positions without keeping syntax nodes.
 * @visibility SqlSemantics
 */
final class IdentifierReader
{
    /**
     * Reads one identifier after the grammar has established its role as a name.
     */
    public function name(Node|Token $source): Name
    {
        $tokens = $source instanceof Token ? [$source] : $source->tokens();
        assert(count($tokens) === 1, 'An identifier has exactly one lexical token.');
        $token = $tokens[0];
        return new Name((new NameRules())->name($token), Quote::tryFrom(substr($token->text, 0, 1)) ?? Quote::None);
    }

    /**
     * Reads an optional schema followed by an object's own identifier.
     */
    public function qualified(Node $source): QualifiedName
    {
        $names = Tree::outer($source, ['nm']);
        assert(count($names) === 1 || count($names) === 2, 'A qualified object name has one or two identifier positions.');
        return count($names) === 1
            ? new QualifiedName($this->name($names[0]))
            : new QualifiedName($this->name($names[1]), $this->name($names[0]));
    }

    /**
     * Reads immediate name positions, excluding names inside a table or expression.
     * @return list<Name>
     */
    public function directNames(Node $source): array
    {
        $names = [];
        foreach ($source->children as $child) {
            if ($child instanceof Node && $child->name === 'nm') {
                $names[] = $this->name($child);
            }
        }
        return $names;
    }
}
