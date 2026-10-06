<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One option of a role membership grant: ADMIN, INHERIT or SET with OPTION, TRUE or FALSE.
 *
 * Mirrors the `DefElem` of `grant_role_opt`. The grammar accepts any label
 * as the option name and the server recognizes `admin`, `inherit` and `set`;
 * another name is reported by the statement.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading a membership option
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT staff TO joe WITH INHERIT FALSE');
 *     [$operation->statement->options[0]->name->value, $operation->statement->options[0]->recognized(), $operation->statement->options[0]->setting->enabled()] // => ['inherit', true, false]
 */
final class MembershipOption implements Node
{
    use Snapshot;

    /**
     * The option names the server recognizes.
     */
    public const RECOGNIZED = ['admin', 'inherit', 'set'];

    /**
     * @param Name $name The option name as decoded
     * @param MembershipSetting $setting The value
     */
    public function __construct(public readonly Name $name, public readonly MembershipSetting $setting)
    {
    }

    /**
     * Tells whether the server recognizes the option name.
     */
    public function recognized(): bool
    {
        return in_array($this->name->value, self::RECOGNIZED, true);
    }

    /**
     * Writes the name and the value.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label)->keyword($this->setting->value);
    }
}
