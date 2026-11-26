<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;

/**
 * The RAISE function of a trigger program: abandon the current row, or fail with a message.
 *
 * Rule: SQLITE-RAISE-001. RAISE(IGNORE) takes no message; every other action
 * takes one. The function yields no value to the expression around it; its
 * facts are those of an absent value.
 * Source: https://sqlite.org/lang_createtrigger.html#the_raise_function.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a message for IGNORE
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Raise(\SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction::Ignore, new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral('x')) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing a missing message for fail
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Raise(\SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction::Fail) // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
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
        $out->keyword('RAISE')->glue()->symbol('(')->keyword($this->action->value);
        if ($this->message !== null) {
            $out->symbol(',')->node($this->message);
        }
        $out->symbol(')');
    }
}
