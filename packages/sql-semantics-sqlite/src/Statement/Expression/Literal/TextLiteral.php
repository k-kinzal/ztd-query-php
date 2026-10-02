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
 * A string literal, kept as its decoded text.
 *
 * Rule: SQLITE-TEXT-LITERAL-001. A string constant in single quotes has
 * storage class TEXT; a quote inside it is written twice.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the decoded text of a string literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT 'it''s'");
 *     [$query->statement->columns[0]->expression->value, $query->toString()] // => ["it's", "SELECT 'it''s'"]
 */
final class TextLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param string $value The decoded text
     */
    public function __construct(public readonly string $value)
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
     * Writes the text in single quotes with each quote doubled.
     */
    public function render(Output $out): void
    {
        $out->spelled("'" . str_replace("'", "''", $this->value) . "'");
    }
}
