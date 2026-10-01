<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Policy\SyntaxRules;
use SqlSemantics\Semantic\Name;

/**
 * Decodes identifiers at the parser boundary.
 * @visibility SqlSemantics
 */
final class Names
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Identifiers $identifiers)
    {
    }

    /**
     * Decodes one identifier without retaining its token.
     */
    public function name(Token $token): Name
    {
        $quote = substr($token->text, 0, 1);
        return new Name($this->identifiers->name($token), in_array($quote, ['"', '`', '[', "'"], true) ? $quote : '');
    }

    /**
     * @return non-empty-list<Name>
     */
    public function parts(Node|Token $node): array
    {
        $tokens = $node instanceof Token ? [$node] : $node->tokens();
        $parts = [];
        foreach ($tokens as $index => $token) {
            if ($index % 2 === 1) {
                if ($token->text !== '.') {
                    Tree::unsupported($node, 'qualified name');
                }
            } else {
                $parts[] = $this->name($token);
            }
        }
        if ($parts === [] || count($tokens) % 2 !== 1) {
            Tree::unsupported($node, 'qualified name');
        }
        return $parts;
    }
    /**
     * @param list<Node|Token> $children
     */
    public function qualified(array $children, SyntaxRules $syntax): bool
    {
        if (!in_array(count($children), [3, 5], true)) {
            return false;
        }
        foreach ($children as $index => $child) {
            if ($index % 2 === 1 && Tree::text($child) !== '.') {
                return false;
            }
            if ($index % 2 === 0 && (!$child instanceof Node || !in_array($child->name, $syntax->nodes('qualifiedPart'), true))) {
                return false;
            }
        }
        return true;
    }
}
