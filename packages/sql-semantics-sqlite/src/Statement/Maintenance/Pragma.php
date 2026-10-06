<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Statement\Type\SignedNumber;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A pragma: a query or a change of a setting or of internal data of the database library.
 *
 * Rule: SQLITE-PRAGMA-001. The grammar fixes the pragma name, an optional
 * schema, and an optional value that is a signed number, a name (written as
 * an identifier or as a string), or one of the keywords ON, DELETE and
 * DEFAULT; these are distinct operand kinds. SQLite hands the value to the
 * pragma as text. `PRAGMA x = v` and
 * `PRAGMA x(v)` "yield identical results"; the value is written with `=`.
 * What a pragma does, whether it returns rows and which columns they have, is
 * fixed per pragma name by the manual and is not derived: the operation
 * records no output, no relation use and no diagnostic, and an unknown pragma
 * name is not an error in SQLite either.
 * Source: https://sqlite.org/pragma.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a pragma with a schema and a value
 *     $pragma = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('PRAGMA main.cache_size(4000)');
 *     [$pragma->statement->name->schema?->value, $pragma->statement->name->name->value, $pragma->statement->value->number->digits, $pragma->toString()] // => ['main', 'cache_size', '4000', 'PRAGMA main.cache_size = 4000']
 */
final class Pragma implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName $name The pragma name with its optional schema
     * @param SignedNumber|Name|PragmaKeyword|null $value The value: a signed number, a name, a keyword, or null when none is written
     */
    public function __construct(public readonly QualifiedName $name, public readonly SignedNumber|Name|PragmaKeyword|null $value = null)
    {
        Check::input($name->catalog === null, 'A pragma name has at most a schema qualifier.');
    }

    /**
     * Derives the numeric literal of the value; what a pragma reads and returns depends on its name, which the grammar does not fix.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->value instanceof SignedNumber) {
            $derivation->scalar($this->value->number, $derivation->environment());
        }
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
        if ($this->value instanceof SignedNumber) {
            $out->node($this->value);
        } elseif ($this->value instanceof Name) {
            $out->name($this->value, NameUse::Label);
        } else {
            $out->keyword($this->value->value);
        }
    }
}
