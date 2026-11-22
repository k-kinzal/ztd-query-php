<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * The VERSION clause of a foreign server: a version string, or NULL to clear it.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createserver.html, https://www.postgresql.org/docs/17/sql-alterserver.html.
 *
 * @visibility SqlSemantics
 * @example Clearing the version
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ServerVersion())->version // => null
 */
final class ServerVersion implements Node
{
    use Snapshot;

    /**
     * @param StringConstant|null $version The version; null for VERSION NULL
     */
    public function __construct(public readonly ?StringConstant $version = null)
    {
    }

    /**
     * Writes VERSION and the version or NULL.
     */
    public function render(Output $out): void
    {
        $out->keyword('VERSION');
        if ($this->version === null) {
            $out->keyword('NULL');

            return;
        }
        $out->node($this->version);
    }
}
