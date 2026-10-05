<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of a built-in function whose name is a keyword and whose arguments are a plain list of expressions.
 *
 * Rule: MYSQL-KEYWORD-CALL-001. The function decides the argument counts
 * the grammar accepts and the result (MYSQL-CALL-RESULT-001); ADDDATE and
 * SUBDATE with a number of days are typed as a date plus or minus days.
 * GROUPING() yields 1 or 0 and is never NULL. The keyword is written against
 * its parenthesis, and the niladic functions are written with empty
 * parentheses, which the grammar accepts for every one of them; CURRENT_USER
 * keeps whether its optional parentheses are written.
 * Terminates: the arguments are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/built-in-function-reference.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing a keyword function
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT LEFT('abc', 2)");
 *     $query->field(0)->type->descriptor->name() // => 'VARCHAR'
 * @example Refusing an argument count the grammar does not accept
 *     new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Left, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class KeywordCall implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param KeywordFunction $function The function
     * @param list<Scalar> $arguments The arguments in order
     * @param OptionalWords $parentheses Whether the empty parentheses of CURRENT_USER are written; the other functions write their parentheses
     */
    public function __construct(public readonly KeywordFunction $function, array $arguments, public readonly OptionalWords $parentheses = OptionalWords::Written)
    {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'Function arguments are expressions.');
        [$minimum, $maximum] = $function->arity();
        Check::input(count($arguments) >= $minimum && ($maximum === -1 || count($arguments) <= $maximum), 'The grammar does not accept this number of arguments for ' . $function->value . '.');
        Check::input($parentheses === OptionalWords::Written || $function === KeywordFunction::CurrentUser, 'Only CURRENT_USER is written without parentheses.');
    }

    /**
     * Derives the arguments and the documented result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = (new Arguments())->one($argument, $derivation, $environment);
        }
        $typing = new ResultTyping();
        if ($this->function === KeywordFunction::AddDate || $this->function === KeywordFunction::SubDate) {
            return new ScalarFact($typing->dateArithmetic($facts[0]->type, IntervalUnit::Day), $typing->nullability('Y', $facts));
        }

        return $typing->fact($this->function->result(), $facts);
    }

    /**
     * Writes the keyword against its parenthesized arguments.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->function->value);
        if ($this->parentheses === OptionalWords::Written) {
            $out->glue()->symbol('(')->list($this->arguments)->symbol(')');
        }
    }
}
