<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

/**
 * The EXTENDED and PARTITIONS keywords of EXPLAIN in MySQL 5.6 and 5.7.
 *
 * In 5.6 EXTENDED adds the `filtered` column and PARTITIONS the
 * `partitions` column; 5.7 always returns both and accepts the keywords
 * for compatibility; 8.0 removed them.
 * Source: https://dev.mysql.com/doc/refman/5.6/en/explain-extended.html,
 * https://dev.mysql.com/doc/refman/5.7/en/explain.html.
 *
 * @visibility public
 * @example Reading the keyword of a modifier
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions->value // => 'PARTITIONS'
 */
enum ExplainModifier: string
{
    case Extended = 'EXTENDED';
    case Partitions = 'PARTITIONS';

    /**
     * Answers the notice owned by this modifier in releases that deprecate it.
     *
     * @example Reading the legacy modifier notice
     *     \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Extended->deprecation(\SqlSemantics\Contract\GrammarRelease::MySql5744)?->code() // => 1681
     */
    public function deprecation(\SqlSemantics\Contract\GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated
    {
        $notice = $this === self::Extended ? \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ExplainExtended : \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ExplainPartitions;
        return $notice->warnedIn($release) ? $notice : null;
    }
}
