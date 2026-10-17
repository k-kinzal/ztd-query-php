<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Server\ForeignStrings;
use SqlSemantics\Platform\MySql\Rules\Text\NameEncoding;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;

/**
 * Preserves foreign-server string conversions before a later input refusal.
 *
 * Only the decoded operand and its warning escape the input boundary. Names
 * and option strings follow the same conversion rule as direct construction.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxForeignStrings
{
    /**
     * Returns the conversion notice of a completed name or text option.
     *
     * @param list<Token> $tokens The input tokens used only to identify a name's role
     * @return list<NameConversion>
     */
    public function converted(Node $node, Lowering $lowering, array $tokens): array
    {
        if ($node->name === 'server_option' && $lowering->form($node)->node(1)->name === 'TEXT_STRING_sys') {
            $decoded = (new NameEncoding())->decode($lowering->literals->bytes($lowering->form($node)->node(1)), $lowering->client);
        } elseif ($node->name === 'ident_or_text' && $this->name($node, $tokens)) {
            $decoded = $lowering->names->decoded($node)->value;
        } else {
            return [];
        }
        $notice = (new ForeignStrings())->conversion($decoded, $lowering->profile->grammar);

        return $notice === null ? [] : [$notice];
    }

    /**
     * Recognizes the name and wrapper positions without retaining input tokens.
     *
     * @param list<Token> $tokens The input tokens
     */
    public function name(Node $node, array $tokens): bool
    {
        $offset = $node->tokens()[0]->offset ?? 0;
        $before = [];
        foreach ($tokens as $token) {
            if ($token->offset >= $offset) {
                break;
            }
            $before[] = $token->name;
            $before = array_slice($before, -3);
        }

        return in_array($before, [['CREATE', 'SERVER_SYM'], ['ALTER', 'SERVER_SYM'], ['DROP', 'SERVER_SYM'], ['FOREIGN', 'DATA_SYM', 'WRAPPER_SYM'], ['SERVER_SYM', 'IF', 'EXISTS']], true)
            || in_array(array_slice($before, -2), [['CREATE', 'SERVER_SYM'], ['ALTER', 'SERVER_SYM'], ['DROP', 'SERVER_SYM']], true);
    }
}
