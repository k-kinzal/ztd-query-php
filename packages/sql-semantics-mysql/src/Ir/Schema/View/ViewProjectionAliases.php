<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks an explicit alias in a MySQL view body.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ViewProjectionAliases
{
    /**
     * Establishes the declaration limit before the server normalizes its heading.
     */
    public function check(string $requested): void
    {
        Check::input(
            preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]{1,64}(?<! )\z/u', $requested) === 1,
            'A MySQL view projection alias needs 1 to 64 BMP characters without a trailing space.'
        );
    }
}
