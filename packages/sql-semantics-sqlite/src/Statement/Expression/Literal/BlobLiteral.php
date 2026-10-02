<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Literal;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A BLOB literal, kept as the hexadecimal digits of its bytes in upper case.
 *
 * Rule: SQLITE-BLOB-LITERAL-001. A BLOB literal has storage class BLOB.
 * Source: https://sqlite.org/lang_expr.html#literal_values_constants_.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the bytes of a BLOB literal
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT x'0aff'");
 *     [$query->statement->columns[0]->expression->hex, $query->toString()] // => ['0AFF', "SELECT x'0AFF'"]
 * @example Refusing half a byte
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\BlobLiteral('ABC') // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
