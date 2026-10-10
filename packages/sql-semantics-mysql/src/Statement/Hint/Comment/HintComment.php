<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Comment;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * One hint comment, `/*+ ... *` . `/`: the hints it holds and the problem it stops at, if any.
 *
 * The server reads a comment that starts with `/*+` right after the
 * keyword SELECT, INSERT, REPLACE, UPDATE or DELETE as a list of hints;
 * a query block or a statement has at most that one comment. The hints are
 * kept in written order; those before a problem count, the rest are not
 * read. Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-syntax.
 *
 * @visibility public
 * @example Reading the hints of a query block
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT /*+ BKA(t) MAX_EXECUTION_TIME(5) *' . '/ 1');
 *     array_map(static fn ($hint) => $hint->text(), $query->statement->hints) // => ['BKA(`t`)', 'MAX_EXECUTION_TIME(5)']
 */
final class HintComment
{
    use Snapshot;

    /**
     * @var list<OptimizerHint> The hints read, in written order
     */
    public readonly array $hints;

    /**
     * @param list<OptimizerHint> $hints The hints read, in written order
     * @param HintError|null $error The problem the comment stops at, or null when it is read to its end
     */
    public function __construct(array $hints, public readonly ?HintError $error = null)
    {
        $this->hints = Check::listOf($hints, OptimizerHint::class, 'A hint comment holds optimizer hints.');
    }

    /**
     * Answers the comment as a statement writes it, or null when it holds no hint.
     *
     * @example Writing a comment
     *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment([new \SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint('q')]))->text() // => '/*+ QB_NAME(`q`) *' . '/'
     */
    public function text(): ?string
    {
        if ($this->hints === []) {
            return null;
        }

        return '/*+ ' . implode(' ', array_map(static fn (OptimizerHint $hint): string => $hint->text(), $this->hints)) . ' */';
    }
}
