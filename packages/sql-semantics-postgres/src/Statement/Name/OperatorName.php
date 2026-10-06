<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The name of an operator, optionally schema-qualified with the `OPERATOR(...)` syntax.
 *
 * An operator is a name looked up in the catalog, not a member of a closed
 * set. `!=` is read as the operator `<>`. The `OPERATOR(...)` syntax is kept
 * because it gives the operator the precedence of "any other operator"
 * whatever operator it names; an expression writes a qualified operator only
 * with that syntax, while commands that name an operator as an object write
 * the qualifiers bare.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-OPERATORS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-OPERATOR-CALLS.
 *
 * @visibility public
 * @example Reading the operator of a comparison
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a FROM t WHERE a != 1');
 *     $query->statement->where->operator->name->value // => '<>'
 */
final class OperatorName implements OptionArgument
{
    use Snapshot;

    /**
     * @var list<Name> The schema qualifiers written before the operator, outermost first
     */
    public readonly array $qualifiers;

    /**
     * @param Name $name The operator symbol
     * @param list<Name> $qualifiers The schema qualifiers, outermost first
     * @param bool $explicit Whether the operator is written with the `OPERATOR(...)` syntax
     */
    public function __construct(public readonly Name $name, array $qualifiers = [], public readonly bool $explicit = false)
    {
        $this->qualifiers = Check::listOf($qualifiers, Name::class, 'Operator qualifiers are names.');
        Check::input(preg_match('/\A[-+*\/<>=~!@#%^&|`?]{1,63}\z/', $name->value) === 1, 'An operator name is 1 to 63 operator characters.');
        Check::input(!str_contains($name->value, '--') && !str_contains($name->value, '/*') && $name->value !== '!=' && $name->value !== '=>', 'An operator name holds no comment start and is neither the alias != nor the token =>.');
        Check::input(preg_match('/[-+]\z/', $name->value) !== 1 || strlen($name->value) === 1 || strpbrk($name->value, '~!@#%^&|`?') !== false, 'A multi-character operator ends in + or - only when it holds one of ~ ! @ # % ^ & | ` ?.');
    }

    /**
     * Derives nothing: an operator name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the qualifiers and the operator, inside `OPERATOR(...)` when written so.
     */
    public function render(Output $out): void
    {
        if ($this->explicit) {
            $out->keyword('OPERATOR')->symbol('(');
        }
        foreach ($this->qualifiers as $qualifier) {
            $out->name($qualifier, NameUse::Qualifier)->symbol('.');
        }
        $out->spelled($this->name->value);
        if ($this->explicit) {
            $out->symbol(')');
        }
    }
}
