<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * How a cast converts without a function: binary coercible (WITHOUT FUNCTION) or through the text forms (WITH INOUT).
 *
 * Source: https://www.postgresql.org/docs/17/sql-createcast.html.
 *
 * @visibility SqlSemantics
 * @example Spelling the I/O conversion
 *     \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\CastConversion::InOut->value // => 'WITH INOUT'
 */
enum CastConversion: string implements Clause
{
    case Binary = 'WITHOUT FUNCTION';
    case InOut = 'WITH INOUT';

    /**
     * Derives nothing: the conversion holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->value));
    }
}
