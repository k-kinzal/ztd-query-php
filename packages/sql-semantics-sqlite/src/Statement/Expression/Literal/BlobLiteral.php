<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Known;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;

/**
 * A BLOB literal, kept as the hexadecimal digits of its bytes in upper case.
 *
 * Rule: SQLITE-BLOB-LITERAL-001. A BLOB literal has storage class BLOB.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing half a byte
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral('ABC') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 * @example Refusing lower case digits
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral('0aff') // throws \SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction
 */
final class BlobLiteral implements Scalar
{
    use Snapshot;

    /**
     * @param string $hex The bytes as upper-case hexadecimal digits, two per byte
     */
    public function __construct(public readonly string $hex)
    {
        Check::input(preg_match('/\A(?:[0-9A-F]{2})*\z/', $hex) === 1, 'A BLOB literal is an even number of upper-case hexadecimal digits.');
    }

    /**
     * Derives BLOB.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Storage::Blob), Nullability::NotNull);
    }

    /**
     * Writes the digits in the BLOB literal quotes.
     */
    public function render(Output $out): void
    {
        $out->spelled("x'" . $this->hex . "'");
    }
}
