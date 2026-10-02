<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The current UTC date, time or timestamp as text.
 *
 * Rule: SQLITE-CURRENT-TIME-001. Each keyword yields a TEXT value in a fixed
 * format and is never NULL.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the type of the current timestamp
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT CURRENT_TIMESTAMP');
 *     $query->field(0)->type->descriptor // => \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Text
 */
final class CurrentTime implements Scalar
{
    use Snapshot;

    /**
     * @param TimeKeyword $keyword The keyword written
     */
    public function __construct(public readonly TimeKeyword $keyword)
    {
    }

    /**
     * Derives TEXT.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Storage::Text), Nullability::NotNull);
    }

    /**
     * Writes the keyword.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->keyword->value);
    }
}
