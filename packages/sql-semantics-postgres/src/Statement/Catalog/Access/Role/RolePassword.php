<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The role option PASSWORD: a password, or NULL to remove the password.
 *
 * The word ENCRYPTED before PASSWORD has no effect and is not kept. The
 * word UNENCRYPTED is still in the grammar, but the server rejects it; it is
 * kept so that the statement reports the problem and is written back as it was.
 * Source: https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility public
 * @example Removing the password of a role
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE joe PASSWORD NULL');
 *     $operation->statement->options[0]->password // => null
 */
final class RolePassword implements RoleOption
{
    use Snapshot;

    /**
     * @param StringConstant|null $password The password; null when NULL is written
     * @param bool $unencrypted Whether UNENCRYPTED is written, which the server rejects
     */
    public function __construct(public readonly ?StringConstant $password, public readonly bool $unencrypted = false)
    {
        Check::input(!$unencrypted || $password !== null, 'UNENCRYPTED PASSWORD is followed by a password.');
    }

    /**
     * Answers the option a password fills.
     */
    public function option(): string
    {
        return 'password';
    }

    /**
     * Writes the option.
     */
    public function render(Output $out): void
    {
        if ($this->unencrypted) {
            $out->keyword('UNENCRYPTED');
        }
        $out->keyword('PASSWORD');
        if ($this->password === null) {
            $out->keyword('NULL');
        }
        $out->node($this->password);
    }
}
