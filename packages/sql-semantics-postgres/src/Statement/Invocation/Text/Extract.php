<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
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
 * `EXTRACT (field FROM source)`: a field of a date/time or interval value.
 *
 * The server calls `pg_catalog.extract('field', source)`. The field is
 * written as a reserved keyword, an identifier or a string constant, and the
 * three spellings are distinct token sequences, so the model keeps which was
 * written. Rule: PG-EXTRACT-001. Facts: those of the call
 * (PG-CALL-RESULT-001; `numeric` for every date/time source type). The
 * result column is named `extract`. An identifier field is written quoted
 * when it is spelled like a keyword, because the grammar takes only an
 * identifier there.
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html#FUNCTIONS-DATETIME-EXTRACT. Status: Implemented.
 *
 * @visibility public
 * @example Reading the field of an extraction
 *     $extract = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Text\Extract(new \SqlSemantics\Statement\Identifier\Name('epoch'), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     [$extract->fieldName(), $extract->outputName()->value] // => ['epoch', 'extract']
 */
final class Extract implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param ExtractUnit|Name|StringConstant $field The field: a keyword, an identifier or a string constant
     * @param Scalar $source The value the field is taken from
     */
    public function __construct(public readonly ExtractUnit|Name|StringConstant $field, public readonly Scalar $source)
    {
    }

    /**
     * Answers the field name the server passes to `extract`.
     */
    public function fieldName(): string
    {
        return match (true) {
            $this->field instanceof ExtractUnit => $this->field->field(),
            $this->field instanceof Name => $this->field->value,
            default => $this->field->value,
        };
    }

    /**
     * Names an unaliased result column after the function the server calls.
     */
    public function outputName(): Name
    {
        return new Name('extract');
    }

    /**
     * Derives the source and the result of `pg_catalog.extract`.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $source = $derivation->scalar($this->source, $environment);

        return (new CallTyping())->catalog($derivation->context, 'extract', [new ScalarFact(new Known(Builtin::Unknown), Nullability::NotNull), $source]);
    }

    /**
     * Writes EXTRACT with the field and the source.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXTRACT')->glue()->symbol('(');
        if ($this->field instanceof ExtractUnit) {
            $out->keyword($this->field->value);
        } elseif ($this->field instanceof StringConstant) {
            $out->node($this->field);
        } else {
            $out->name($this->field, NameUse::Identifier);
        }
        $out->keyword('FROM')->node($this->source)->symbol(')');
    }
}
