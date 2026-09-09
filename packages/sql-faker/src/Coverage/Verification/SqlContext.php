<?php

declare(strict_types=1);

namespace SqlFaker\Coverage\Verification;

use SqlFaker\Coverage\CoverageException;

/**
 * A declared surrounding SQL context whose exact text forms part of the verification identity.
 */
final class SqlContext
{
    /**
     * Wrappers add a parser entry context without changing the generated fragment.
     */
    public function __construct(public readonly string $prefix = '', public readonly string $suffix = '')
    {
    }

    /**
     * Builds the exact statement sent to the independent parser.
     */
    public function sql(string $fragment): string
    {
        return $this->prefix . $fragment . $this->suffix;
    }

    /**
     * Recovers the generated fragment only after checking both declared wrappers.
     * @throws CoverageException When the checked SQL does not have this context
     */
    public function fragment(string $sql): string
    {
        $length = strlen($sql) - strlen($this->prefix) - strlen($this->suffix);
        if ($length < 0 || !str_starts_with($sql, $this->prefix) || !str_ends_with($sql, $this->suffix)) {
            throw new CoverageException('Checked SQL does not match its declared wrapper.');
        }
        return substr($sql, strlen($this->prefix), $length);
    }
}
