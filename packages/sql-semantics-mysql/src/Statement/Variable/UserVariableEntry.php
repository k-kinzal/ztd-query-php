<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored user variable's value type and assignment character set.
 *
 * Numeric values retain the connection collation at assignment separately from their
 * binary value collation. MySQL 5.6 and 5.7 use its character width in integer result
 * metadata, even after SET NAMES changes the connection. A typed NULL can change
 * the read domain while preserving a preceding binary-string interpretation;
 * native argument checks distinguish these facts. Verified through PDO on
 * Testcontainers MySQL 5.6.51 and 5.7.44.
 *
 * @visibility public
 * @example Describing an integer assigned in a latin1 connection
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('latin1_swedish_ci'));
 *     $type->collation->charset->name // => 'latin1'
 */
final class UserVariableEntry
{
    use Snapshot;

    /**
     * @param Domain $domain The type of the stored value
     * @param Collation $collation The collation retained when the variable was assigned
     * @param bool|null $binaryString The binary-string interpretation retained across a NULL assignment; null when determined by the current domain
     */
    public function __construct(public readonly Domain $domain, public readonly Collation $collation, public readonly ?bool $binaryString = null)
    {
    }
}
