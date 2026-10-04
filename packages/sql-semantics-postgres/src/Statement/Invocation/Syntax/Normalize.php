<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * `NORMALIZE (value [, form])`: a string converted to a Unicode normal form.
 *
 * The server calls `pg_catalog.normalize(value[, 'form'])`; without a form
 * the function's default NFC applies, but the form written is kept. Rule:
 * PG-NORMALIZE-001. Facts: those of the call (PG-CALL-RESULT-001, the form
 * passed as a `text` constant). The result column is named `normalize`.
 * Source: https://www.postgresql.org/docs/17/functions-string.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a normalization
 *     $normalize = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Syntax\Normalize(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\PostgreSql\Statement\Option\NormalForm::Nfkc);
 *     [$normalize->form->value, $normalize->outputName()->value] // => ['NFKC', 'normalize']
 */
final class Normalize implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $value The string normalized
     * @param NormalForm|null $form The normal form written
     */
    public function __construct(public readonly Scalar $value, public readonly ?NormalForm $form = null)
    {
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('normalize');
    }

    /**
     * Derives the value and the result of `pg_catalog.normalize`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $arguments = [$derivation->scalar($this->value, $environment)];
        if ($this->form !== null) {
            $arguments[] = new ScalarFact(new Known(Builtin::Unknown), Nullability::NotNull);
        }

        return (new CallTyping())->catalog($derivation->context, 'normalize', $arguments);
    }

    /**
     * Writes NORMALIZE with the value and the form.
     */
    public function render(Output $out): void
    {
        $out->keyword('NORMALIZE')->glue()->symbol('(')->node($this->value);
        if ($this->form !== null) {
            $out->symbol(',')->keyword($this->form->value);
        }
        $out->symbol(')');
    }
}
