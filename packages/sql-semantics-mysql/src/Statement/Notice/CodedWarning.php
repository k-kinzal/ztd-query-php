<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Statement\Fact\Warning;

/**
 * A resolved MySQL warning with its client-visible condition number.
 *
 * Consumers can report the condition without classifying individual warning
 * implementations or re-evaluating the inputs that caused it.
 *
 * @visibility public
 * @example Reading a conversion condition number
 *     (new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new \SqlSemantics\Statement\Identifier\Name('😀')))->code() // => 1300
 */
interface CodedWarning extends Warning
{
    /**
     * Returns the MySQL condition number.
     */
    public function code(): int;
}
