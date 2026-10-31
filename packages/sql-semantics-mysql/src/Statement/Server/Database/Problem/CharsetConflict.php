<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database\Problem;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A database character-set option disagrees with the earlier resolved charset.
 *
 * The earlier charset can also have been selected by a collation option.
 *
 * @visibility public
 * @example Retaining the resolved names
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1'), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4')))->later->name // => 'utf8mb4'
 */
final class CharsetConflict implements Diagnostic
{
    use Snapshot;

    /**
     * @param Charset $earlier The charset already selected
     * @param Charset $later The conflicting charset
     */
    public function __construct(public readonly Charset $earlier, public readonly Charset $later)
    {
    }

    /**
     * Describes the resolved character sets in occurrence order.
     */
    public function message(): string
    {
        return "Conflicting declarations: 'CHARACTER SET " . $this->earlier->name . "' and 'CHARACTER SET " . $this->later->name . "'";
    }
}
