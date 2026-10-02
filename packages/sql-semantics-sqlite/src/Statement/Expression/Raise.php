<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * The RAISE function of a trigger program: abandon the current row, or fail with a message.
 *
 * Rule: SQLITE-RAISE-001. RAISE(IGNORE) takes no message; every other action
 * takes one. The function yields no value to the expression around it; its
 * facts are those of an absent value.
 * Source: https://sqlite.org/lang_createtrigger.html#the_raise_function.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the message of a RAISE
 *     $trigger = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("CREATE TRIGGER r BEFORE DELETE ON t BEGIN SELECT RAISE(FAIL, 'no'); END");
 *     $trigger->statement->steps[0]->columns[0]->expression->message->value // => 'no'
 * @example Refusing a message for IGNORE
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Raise(\SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction::Ignore, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral('x')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Raise implements Scalar
{
    use Snapshot;

    /**
     * @param RaiseAction $action The action
     * @param Scalar|null $message The error message; absent exactly for IGNORE
     */
    public function __construct(public readonly RaiseAction $action, public readonly ?Scalar $message = null)
    {
        Check::input(($action === RaiseAction::Ignore) === ($message === null), 'RAISE takes a message for every action except IGNORE.');
    }

    /**
     * Derives the message; the function itself yields no value.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->message !== null) {
            $derivation->scalar($this->message, $environment);
        }

        return new ScalarFact(new NullOnly(), Nullability::Nullable);
    }

    /**
     * Writes the function.
     */
    public function render(Output $out): void
    {
        $out->keyword('RAISE')->symbol('(')->keyword($this->action->value);
        if ($this->message !== null) {
            $out->symbol(',')->node($this->message);
        }
        $out->symbol(')');
    }
}
