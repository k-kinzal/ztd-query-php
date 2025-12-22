<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\IdentifierQuoter;

/**
 * Fake IdentifierQuoter using double-quote style.
 */
final class FakeIdentifierQuoter implements IdentifierQuoter
{
    public function quote(string $identifier): string
    {
        $escaped = str_replace('"', '""', $identifier);

        return '"' . $escaped . '"';
    }
}
