<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A string literal, kept as its decoded text.
 *
 * Rule: SQLITE-TEXT-LITERAL-001. A string constant in single quotes has
 * storage class TEXT; a quote inside it is written twice.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
