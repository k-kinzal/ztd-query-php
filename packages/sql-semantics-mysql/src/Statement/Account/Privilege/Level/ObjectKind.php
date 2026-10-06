<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

/**
 * The kind of object the ON clause of GRANT and REVOKE names: a table (the default), a stored function or a stored procedure.
 *
 * Mirrors Acl_type. `ON TABLE` and `ON` without a kind are the same request.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-object-quoting.
 *
 * @visibility public
 * @example Reading the keyword of a kind
 *     [\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure->keyword(), \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table->keyword()] // => ['PROCEDURE', null]
 */
enum ObjectKind
{
    case Table;
    case Function;
    case Procedure;

    /**
     * Answers the keyword the writer emits: none for a table.
     */
    public function keyword(): ?string
    {
        return match ($this) {
            self::Table => null,
            self::Function => 'FUNCTION',
            self::Procedure => 'PROCEDURE',
        };
    }
}
