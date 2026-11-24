<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Maintenance;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;

/**
 * A pragma: a query or a change of a setting or of internal data of the database library.
 *
 * Rule: SQLITE-PRAGMA-001. The grammar fixes the pragma name, an optional
 * schema, and an optional value that is a signed number, a name (written as
 * an identifier or as a string), or one of the keywords ON, DELETE and
 * DEFAULT; these are distinct operand kinds. SQLite hands the value to the
 * pragma as text. `PRAGMA x = v` and
 * `PRAGMA x(v)` "yield identical results"; the value is written with `=`.
 * The parsed request defers the meaning of every argument to the rule for
 * the resolved pragma name, including arguments written as numeric tokens.
 * Source: https://sqlite.org/pragma.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Pragma implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $name The pragma name with its optional schema
     * @param PragmaNumber|Name|PragmaKeyword|null $value The value: a signed number, a name, a keyword, or null when none is written
     */
    public function __construct(public readonly QualifiedName $name, public readonly PragmaNumber|Name|PragmaKeyword|null $value = null)
    {
        Check::input($name->catalog === null, 'A pragma name has at most a schema qualifier.');
    }

    /**
     * Leaves pragma-specific interpretation to the resolved name rule.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the pragma, with the value after an equal sign.
     */
    public function render(Output $out): void
    {
        $out->keyword('PRAGMA');
        (new ObjectNames())->write($out, $this->name, NameUse::Label);
        if ($this->value === null) {
            return;
        }
        $out->symbol('=');
        if ($this->value instanceof PragmaNumber) {
            $out->node($this->value);
        } elseif ($this->value instanceof Name) {
            $out->name($this->value, NameUse::Label);
        } else {
            $out->keyword($this->value->value);
        }
    }
}
