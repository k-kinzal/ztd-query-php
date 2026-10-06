<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A tablespace or log file group option that breaks a rule the server checks while it parses the statement.
 *
 * The server rejects the statement with the error of the rule.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Describing a repeated option
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem(\SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule::RepeatedOption, 'COMMENT'))->message() // => 'The COMMENT option is written more than once (ER_FILEGROUP_OPTION_ONLY_ONCE).'
 */
final class StorageProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param StorageRule $rule The broken rule
     * @param string $option The keyword of the option, such as COMMENT or INITIAL_SIZE
     */
    public function __construct(public readonly StorageRule $rule, public readonly string $option)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        $text = match ($this->rule) {
            StorageRule::RepeatedOption => 'The ' . $this->option . ' option is written more than once',
            StorageRule::WrongSize => 'The size of ' . $this->option . ' is a number with an optional K, M or G multiplier',
            StorageRule::SizeOverflow => 'The size of ' . $this->option . ' overflows: the number before the multiplier must be below 2^31',
        };

        return $text . ' (' . $this->rule->value . ').';
    }
}
