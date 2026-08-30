<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Dialect;

use PhpMyAdmin\SqlParser\Components\OptionsArray;

/**
 * Reports which of a statement's optional words were written.
 *
 * The parser answers an option that was not written with false, and one that
 * was written without a value with true -- so a plain truth test on the answer
 * is wrong for an option whose value is the empty string. Asking here means
 * every caller asks the same way.
 */
final class MySqlStatementOptions
{
    /**
     * Reports whether an option was written.
     *
     * @param OptionsArray $options Options the parser read off the statement
     * @param string $name Option to look for
     *
     * @return bool True when the statement wrote it
     */
    public function isSet(OptionsArray $options, string $name): bool
    {
        return $options->has($name) !== false;
    }
}
