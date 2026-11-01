<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Storage;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Preserves storage operand checks before a later syntax error.
 *
 * Sizes and undo-tablespace options are checked while reading. MySQL 5.x also
 * checks other duplicate settings there; modern ordinary tablespaces and
 * log file groups first finish parsing the whole statement. Observed with ordinary statements,
 * SHOW PARSE_TREE and malformed tails on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxStorageOptions
{
    /**
     * Returns a completed size refusal or an early option-list conflict.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name === 'size_number') {
            $size = $lowering->numbers->size($node);
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Server\StorageSizes())->problem($size->number, $size->word);
            return $problem === null ? null : new ParseFailure($problem, true);
        }
        if ((!in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && $node->name !== 'undo_tablespace_option_list')
            || !in_array($node->name, ['undo_tablespace_option_list', 'tablespace_options', 'alter_tablespace_options', 'logfile_group_options', 'alter_logfile_group_options', 'change_ts_options', 'drop_ts_options'], true)) {
            return null;
        }
        try {
            $options = (new StorageOptionRule($lowering))->options($node);
            $problem = (new \SqlSemantics\Platform\MySql\Rules\Server\StorageOptions())->repeated($options, in_array($lowering->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true));
            if ($problem !== null) {
                return new ParseFailure($problem, true);
            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, true);
        }

        return null;
    }
}
