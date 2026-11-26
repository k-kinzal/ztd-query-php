<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One option of a role membership grant: ADMIN, INHERIT or SET with OPTION, TRUE or FALSE.
 *
 * Mirrors the `DefElem` of `grant_role_opt`. The grammar accepts any label
 * as the option name and the server recognizes `admin`, `inherit` and `set`;
 * another name is reported by the statement.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility SqlSemantics
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
