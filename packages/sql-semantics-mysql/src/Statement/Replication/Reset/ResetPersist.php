<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Reset;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;

/**
 * `RESET PERSIST [[IF EXISTS] [component.]variable]`: removes persisted global system variable settings (MySQL 8.0 and later).
 *
 * Mirrors SQLCOM_RESET with REFRESH_PERSIST: without a variable every
 * persisted setting is removed. A component variable is written
 * `component.variable`; the server joins the two names with a dot, and
 * `DEFAULT.variable` names the component `default`. IF EXISTS turns the error
 * for a variable that is not persisted into a warning. Rule:
 * MYSQL-RESET-PERSIST-001. The statement has no facts of its own.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/reset-persist.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ResetPersist implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Name|null $variable The variable whose setting is removed; null for every setting
     * @param Name|null $component The component that defines the variable, when written
     */
    public function __construct(public readonly bool $ifExists = false, public readonly ?Name $variable = null, public readonly ?Name $component = null)
    {
        Check::input($variable !== null || (!$ifExists && $component === null), 'IF EXISTS and a component need a variable.');
    }

    /**
     * Checks that the release has persisted variables.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        Check::input(!(new Releases())->legacy($derivation->context->profile->grammar), 'RESET PERSIST needs MySQL 8.0 or later.');
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RESET', 'PERSIST');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        if ($this->component !== null) {
            $out->name($this->component, NameUse::Qualifier)->symbol('.');
        }
        if ($this->variable !== null) {
            $out->name($this->variable, NameUse::Identifier);
        }
    }
}
