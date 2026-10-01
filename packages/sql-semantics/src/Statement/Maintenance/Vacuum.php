<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Maintenance;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

/**
 * Requests an in-place SQLite database rebuild, without applying it during analysis.
 * @visibility public
 * @example Identifying the database being rebuilt
 *     (new \SqlSemantics\Statement\Maintenance\Vacuum())->schema->value // => 'main'
 */
final class Vacuum implements Operation
{
    /**
     * An omitted schema denotes main; the semantic target is always explicit.
     */
    public function __construct(public readonly Name $schema = new Name('main'))
    {
    }

    /**
     * SQLite ignores a request targeting temp, including any destination expression.
     */
    public function isNoOp(): bool
    {
        return strcasecmp($this->schema->value, 'temp') === 0;
    }

    /**
     * Reconstructs the request from its database target.
     */
    public function toString(): string
    {
        return 'VACUUM ' . $this->schema->toString();
    }
}
