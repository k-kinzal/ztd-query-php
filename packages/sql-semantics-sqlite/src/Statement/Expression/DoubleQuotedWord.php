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
 * An unqualified word in double quotes used as a value: a column when one resolves, otherwise a string.
 *
 * This is a different request from a column use: SQLite first looks the word
 * up as a column name and, when no column of that name is visible, reads the
 * word as a string literal instead of reporting an error.
 *
 * Rule: SQLITE-DOUBLE-QUOTED-WORD-001. Facts: when the lookup of
 * SQLITE-COLUMN-LOOKUP-001 finds a column or a result alias, the facts of
 * that column; when it certainly finds nothing, TEXT and never NULL with no
 * resolution; when the answer depends on missing inputs, a conditional
 * resolution and a dependent type.
 * Source: https://sqlite.org/quirks.html#dblquote. Status: Implemented.
 *
 * @visibility public
 * @example Reading a double-quoted word that is no column as text
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $query = $semantics->analyze('SELECT "a", "b" FROM t', [$table]);
 *     [$query->field(0)->column() === $table->declarations()[0]->columns[0], $query->field(1)->type->descriptor, $query->toString()] // => [true, \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Text, 'SELECT "a", "b" FROM t']
 */
final class DoubleQuotedWord implements Scalar
{
    use Snapshot;

    /**
     * @param Name $word The decoded word
     */
    public function __construct(public readonly Name $word)
    {
    }

    /**
     * Resolves the word as a column and falls back to a string when no column of that name is visible.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $resolution = (new ColumnResolver())->find($environment, $this->word);
        if ($resolution instanceof MissingColumn) {
            return new ScalarFact(new Known(Storage::Text), Nullability::NotNull);
        }

        return (new ColumnFacts())->of($resolution);
    }

    /**
     * Writes the word in double quotes with each double quote doubled.
     */
    public function render(Output $out): void
    {
        $out->spelled('"' . str_replace('"', '""', $this->word->value) . '"');
    }
}
