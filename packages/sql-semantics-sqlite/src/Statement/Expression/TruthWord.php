<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The bare word TRUE or FALSE used as a value: a column of that name when one resolves, otherwise the integer 1 or 0.
 *
 * Rule: SQLITE-TRUTH-WORD-001. TRUE and FALSE are no keywords. SQLite looks
 * the unquoted, unqualified word up as a column first and reads it as the
 * boolean constant only when no such column is visible. Facts: those of the
 * column when one resolves; INTEGER and never NULL when none can; dependent
 * when the answer depends on missing inputs.
 * Source: https://sqlite.org/lang_expr.html#boolean_expressions. Status: Implemented.
 *
 * @visibility public
 * @example Reading TRUE as the integer constant
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT true', []);
 *     [$query->statement->columns[0]->expression->value, $query->field(0)->type->descriptor, $query->toString()] // => [true, \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer, 'SELECT TRUE']
 */
final class TruthWord implements Scalar
{
    use Snapshot;

    /**
     * @param bool $value Whether the word is TRUE
     */
    public function __construct(public readonly bool $value)
    {
    }

    /**
     * Resolves the word as a column and falls back to the constant when no column of that name is visible.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $resolution = (new ColumnResolver())->find($environment, new Name($this->value ? 'true' : 'false'));
        if ($resolution instanceof MissingColumn) {
            return new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        }

        return (new ColumnFacts())->of($resolution);
    }

    /**
     * Writes the bare word.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->value ? 'TRUE' : 'FALSE');
    }
}
