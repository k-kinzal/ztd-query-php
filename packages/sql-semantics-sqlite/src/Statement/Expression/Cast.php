<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\TypeNameAffinity;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Lexical;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\NullOnly;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Storages;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Platform\Sqlite\Statement\Type\TypeName;

/**
 * A conversion of a value to the storage class the affinity of a type name selects.
 *
 * Rule: SQLITE-CAST-001. The affinity of the type name decides the result:
 * INTEGER, TEXT, BLOB and REAL affinity give that storage class; NUMERIC
 * affinity, which an absent type name also has, gives INTEGER or REAL. A NULL
 * operand stays NULL, so the NULL fact is that of the operand.
 * Source: https://sqlite.org/lang_expr.html#castexpr. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Cast implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The converted expression
     * @param TypeName|null $target The type name; null when none is written
     */
    public function __construct(
        public readonly Scalar $operand,
        public readonly ?TypeName $target = null,
        public readonly ?Layout $typeLayout = null,
    ) {
    }

    /**
     * Answers the decoded type token SQLite compares, including its observed internal spelling.
     */
    public function nativeType(): string
    {
        if ($this->target === null) {
            return '';
        }
        $first = $this->target->words[0];
        if (!$first->bare()) {
            return $first->name->value;
        }

        if ($this->typeLayout !== null) {
            return $this->typeLayout->text();
        }

        $output = new Output(new Codec());
        $this->target->render($output);

        return (new Lexical())->join($output->pieces());
    }

    /**
     * Derives the storage class from the affinity of the type name.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);
        foreach ($this->target === null ? [] : $this->target->arguments as $argument) {
            $derivation->scalar($argument->number, $environment);
        }
        if ($fact->type instanceof NullOnly) {
            return new ScalarFact($fact->type, $fact->nullability);
        }
        $storages = match ((new TypeNameAffinity())->conversion($this->nativeType())) {
            'INTEGER' => [Storage::Integer],
            'TEXT' => [Storage::Text],
            'BLOB' => [Storage::Blob],
            'REAL' => [Storage::Real],
            'NUMERIC' => [Storage::Integer, Storage::Real],
        };

        return new ScalarFact((new Storages())->fact($storages), $fact->nullability);
    }

    /**
     * Writes the conversion.
     */
    public function render(Output $out): void
    {
        $out->keyword('CAST')->glue()->symbol('(')->node($this->operand)->keyword('AS');
        if ($this->target !== null) {
            $out->layout($this->typeLayout, $this->target);
        }
        $out->symbol(')');
    }
}
