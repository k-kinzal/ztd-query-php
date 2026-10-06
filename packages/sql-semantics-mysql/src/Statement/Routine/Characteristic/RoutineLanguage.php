<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The LANGUAGE characteristic of a stored routine: SQL, or from MySQL 8.1 the name of an external language.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/9.1/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading the language of a routine
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER FUNCTION f LANGUAGE SQL');
 *     $alter->statement->characteristics[0]->external // => null
 */
final class RoutineLanguage implements Characteristic
{
    use Snapshot;

    /**
     * @param Name|null $external The name of the external language; null for the keyword SQL
     */
    public function __construct(public readonly ?Name $external = null)
    {
    }

    /**
     * Writes LANGUAGE and the keyword SQL or the language name.
     */
    public function render(Output $out): void
    {
        $out->keyword('LANGUAGE');
        if ($this->external === null) {
            $out->keyword('SQL');
        } else {
            $out->name($this->external, NameUse::Identifier);
        }
    }
}
