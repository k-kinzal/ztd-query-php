<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Hint;

use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;

/**
 * Finds the hint comments of a parse tree and reads each into its hints.
 *
 * Rule: MYSQL-OPTIMIZER-HINTS-001. From MySQL 5.7 on, the lexer of the
 * server reads a comment that starts with `/*+` and follows, after
 * whitespace only, the keyword SELECT, INSERT, REPLACE, UPDATE or DELETE as
 * a hint comment, wherever the keyword is: also in ON DUPLICATE KEY UPDATE,
 * FOR UPDATE or a REPLACE() call, where the hints have no effect but a
 * problem in the comment still warns. Any other comment, and every comment
 * in MySQL 5.6, is an ordinary comment. The comment ends at the first
 * `*` . `/` (verified on live 5.6.51, 5.7.44 and 8.4 servers). Terminates:
 * one pass over the tokens.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-syntax.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the hint comments of a statement
 *     $tree = (new \SqlParser\MySql\MySqlParser('mysql-8.4.7'))->parse('SELECT /*+ BKA(t) *' . '/ 1');
 *     array_map(static fn ($comment) => $comment->text(), (new \SqlSemantics\Platform\MySql\Lowering\Hint\HintReader(\SqlSemantics\Contract\GrammarRelease::MySql847))->comments($tree)) // => [0 => '/*+ BKA(`t`) *' . '/']
 */
final class HintReader
{
    /**
     * The tokens of the keywords a hint comment follows.
     */
    public const KEYWORDS = ['SELECT_SYM' => true, 'INSERT' => true, 'INSERT_SYM' => true, 'REPLACE' => true, 'REPLACE_SYM' => true, 'UPDATE_SYM' => true, 'DELETE_SYM' => true];

    /**
     * @param GrammarRelease $release The release whose lexer reads the comments
     * @param bool $ansiQuotes Whether double quotes enclose names (ANSI_QUOTES)
     */
    public function __construct(public readonly GrammarRelease $release, public readonly bool $ansiQuotes = false)
    {
    }

    /**
     * Answers the hint comments of a parse tree, by the offset of the keyword each follows, in written order.
     *
     * @return array<int, HintComment>
     */
    public function comments(Node $tree): array
    {
        if ($this->release === GrammarRelease::MySql5651) {
            return [];
        }
        $comments = [];
        $previous = null;
        foreach ($tree->tokens() as $token) {
            if ($previous !== null && isset(self::KEYWORDS[$previous->name])) {
                $lead = ltrim($token->leading, " \t\n\r\f\v");
                $close = str_starts_with($lead, '/*+') ? strpos($lead, '*/', 3) : false;
                if ($close !== false) {
                    $start = $token->offset - strlen($lead) + 3;
                    $comments[$previous->offset] = (new HintParser(new HintLexer(substr($lead, 3, $close - 3), $start, $this->release, $this->ansiQuotes)))->parse();
                }
            }
            $previous = $token;
        }

        return $comments;
    }
}
