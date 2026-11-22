<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnFacts;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\ColumnResolver;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

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
 * @visibility SqlSemantics
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
