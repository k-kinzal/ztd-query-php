<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * `ON db.*`: the database level of a named database.
 *
 * The database name may contain the `_` and `%` wildcards of a pattern, as
 * the grant tables store it; the name is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-database-privileges.
 *
 * @visibility public
 * @example Reading the database
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('GRANT SELECT ON shop.* TO u')->level->database->value // => 'shop'
 */
final class DatabaseLevel implements PrivilegeLevel
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $database The database name
     */
    public function __construct(public readonly Name $database, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
    }

    /**
     * Describes the level.
     */
    public function describe(): string
    {
        return 'database ' . $this->database->value;
    }

    /**
     * Writes `db.*`.
     */
    public function render(Output $out): void
    {
        $out->name($this->database, NameUse::Qualifier)->glue()->symbol('.')->glue()->symbol('*');
    }
}
