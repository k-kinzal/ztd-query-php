<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Spelling;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The written spelling of a region whose spelling the database makes observable.
 *
 * Rule: CORE-SPELLING-001. Some databases name an unaliased result column
 * after the text of its expression (SQLite, MySQL). Where a database does so,
 * the text is part of the meaning, and the model keeps it as a layout: one
 * spelled token per token the rendering of the region produces, in order.
 * The tokens themselves still come from the structure; a layout only chooses
 * the trivia between them and after the last of them, and an equivalent
 * spelling of each. Publication
 * refuses a layout that does not align one to one with the rendered tokens,
 * whose spellings are not the same tokens, or whose gaps are not trivia, and
 * the rendered SQL must lower to the same structure, layout included. A layout
 * never stands for structure, and nothing is rendered from it alone.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Reading the text a region was written as
 *     $layout = new \SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout([new \SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Spelled('', '1'), new \SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Spelled('', '+'), new \SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Spelled('', '1')]);
 *     $layout->text() // => '1+1'
 */
final class Layout
{
    use Snapshot;

    /**
     * @var non-empty-list<Spelled> The spelled tokens in order
     */
    public readonly array $tokens;

    /**
     * @param list<Spelled> $tokens The spelled tokens in order; at least one
     * @param string $trail The whitespace and comments written after the last token, before whatever follows the region
     */
    public function __construct(array $tokens, public readonly string $trail = '')
    {
        $this->tokens = Check::listOf($tokens, Spelled::class, 'A layout spells at least one token.', 1);
    }

    /**
     * Answers the text of the region from its first to its last token.
     */
    public function text(): string
    {
        $text = '';
        foreach ($this->tokens as $position => $token) {
            $text .= ($position === 0 ? '' : $token->gap) . $token->text;
        }

        return $text;
    }
}
