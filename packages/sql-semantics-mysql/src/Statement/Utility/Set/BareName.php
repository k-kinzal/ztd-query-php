<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\ColumnResolver;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A bare name written as the value of a variable assignment in SET, such as `TRADITIONAL` in `SET sql_mode = TRADITIONAL`.
 *
 * Rule: MYSQL-SET-WORD-001. When the assigned variable is a system variable,
 * the server does not resolve the name as a column: it assigns the text of
 * the last name part (`b` for `a.b`) as a string. Facts: assigned to a
 * system variable (`@@x`, or a name with a scope keyword), the value is a
 * known non-NULL VARCHAR; assigned to a name without a scope, which inside a
 * stored program can be a declared variable whose value is an expression,
 * it depends on the declarations of the enclosing program (ProgramVariable).
 * In a trigger body `NEW.x` and `OLD.x` are a column of the row whatever
 * the variable (the parser makes them Item_trigger_field, which is not
 * turned into text), so they resolve as a column name does
 * (MYSQL-COLUMN-LOOKUP-001). The qualifiers are kept and written back.
 * Terminates: no child is derived.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html,
 * `set_var::set_var` in sql/set_var.cc ("If the set value is a field, change
 * it to a string to allow things like SET table_type=MYISAM;").
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a word assigned to a system variable
 *     $set = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SET @@sql_mode = TRADITIONAL');
 *     [$set->statement->items[0]->value->word->value, $set->facts->scalar($set->statement->items[0]->value)->type->descriptor->name()] // => ['TRADITIONAL', 'VARCHAR']
 */
final class BareName implements Scalar
{
    use Snapshot;

    /**
     * @param Name $word The last name part, whose text is the value
     * @param QualifiedName|null $qualifier The name parts written before it
     * @param bool $system Whether the assigned variable is known to be a system variable
     */
    public function __construct(public readonly Name $word, public readonly ?QualifiedName $qualifier = null, public readonly bool $system = true)
    {
        Check::input($qualifier?->catalog === null, 'A word value has at most two qualifiers.');
    }

    /**
     * Derives a known string for a system variable, and the dependence on the enclosing program otherwise.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $resolver = new ColumnResolver();
        if ($this->qualifier !== null && $resolver->row($environment, $this->qualifier)) {
            return $resolver->fact($environment, $this->word, $this->qualifier);
        }
        if ($this->system) {
            return new ScalarFact(new Known(new Character(CharacterKind::VarChar)), Nullability::NotNull);
        }

        return new ScalarFact(new Dependent([new ProgramVariable($this->word)]), Nullability::Dependent);
    }

    /**
     * Writes the name parts separated by dots.
     */
    public function render(Output $out): void
    {
        if ($this->qualifier?->schema !== null) {
            $out->name($this->qualifier->schema, NameUse::Qualifier)->symbol('.');
        }
        if ($this->qualifier !== null) {
            $out->name($this->qualifier->name, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->word, NameUse::Column);
    }
}
