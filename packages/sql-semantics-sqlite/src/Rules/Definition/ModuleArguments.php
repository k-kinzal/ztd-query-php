<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Lowering\Lists;

/**
 * Reads the argument texts of a virtual table module from a parse tree, and checks a text against the grammar.
 *
 * Rule: SQLITE-MODULE-ARGUMENT-TEXT-001. The arguments are the items of the
 * argument list, separated by its top-level commas. The text of an argument
 * is the text of its first token followed, for each further token, by the
 * white space and comments before that token and the token itself: the span
 * SQLite hands to the module. A text is one argument when the grammar, given
 * the text between the parentheses of a virtual table definition, reads
 * exactly one argument with that span and nothing before or after it.
 * Terminates: the list is flattened iteratively and a text is parsed once.
 * Source: https://sqlite.org/lang_createvtab.html (and `sqlite3VtabArgExtend()`
 * in vtab.c of the release). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class ModuleArguments
{
    private static ?SqliteParser $parser = null;

    /**
     * Answers the text of each argument of a `vtabarglist` node, in order.
     *
     * @return list<string>
     */
    public function texts(Node $list): array
    {
        $texts = [];
        foreach ((new Lists())->items($list) as $argument) {
            $text = '';
            foreach ($this->tokens($argument) as $position => $token) {
                $text .= $position === 0 ? $token->text : $token->toString();
            }
            $texts[] = $text;
        }

        return $texts;
    }

    /**
     * Answers the tokens under a node in text order, without recursing on the tree.
     *
     * @return list<Token>
     */
    public function tokens(Node $node): array
    {
        $tokens = [];
        $pending = [$node];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current instanceof Token) {
                $tokens[] = $current;
            } else {
                array_push($pending, ...array_reverse($current->children));
            }
        }

        return $tokens;
    }

    /**
     * Tells whether SQLite reads a text as exactly one argument with that text.
     */
    public function single(string $text): bool
    {
        if ($text === '') {
            return true;
        }
        self::$parser ??= new SqliteParser();
        try {
            $tree = self::$parser->parse('CREATE VIRTUAL TABLE t USING m(' . $text . ')');
        } catch (SourceException) {
            return false;
        }
        $lists = $tree->find('vtabarglist');
        $commands = $tree->find('ecmd');
        if (count($commands) !== 1 || $lists === [] || $this->texts($lists[0]) !== [$text]) {
            return false;
        }
        $inner = $this->tokens($lists[0]);
        $closing = array_slice($this->tokens($tree), -2, 1);

        return $inner[0]->leading === '' && $closing[0]->leading === '';
    }
}
