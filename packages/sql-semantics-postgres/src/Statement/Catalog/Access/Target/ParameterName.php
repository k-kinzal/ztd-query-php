<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;

/**
 * The name of a configuration parameter as an object of a GRANT or REVOKE, such as `work_mem` or `myext.setting`.
 *
 * The server joins the parts with dots into one parameter name.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility SqlSemantics
 * @example Joining the parts of a parameter name
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\ParameterName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('myext'), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('setting')]))->parameter() // => 'myext.setting'
 */
final class ParameterName implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The parts in the order written
     */
    public readonly array $parts;

    /**
     * @param list<Name> $parts The parts in the order written, at least one
     */
    public function __construct(array $parts)
    {
        $this->parts = Check::listOf($parts, Name::class, 'A parameter name has at least one part.', 1);
    }

    /**
     * Answers the parameter name the server receives.
     */
    public function parameter(): string
    {
        $values = [];
        foreach ($this->parts as $part) {
            $values[] = $part->value;
        }

        return implode('.', $values);
    }

    /**
     * Writes the parts separated by dots.
     */
    public function render(Output $out): void
    {
        (new Spelling())->columns($out, $this->parts);
    }
}
