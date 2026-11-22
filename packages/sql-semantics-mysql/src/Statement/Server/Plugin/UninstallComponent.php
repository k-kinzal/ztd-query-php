<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * `UNINSTALL COMPONENT 'urn', …`: a request to unload server components (MySQL 8.0 and later).
 *
 * Mirrors Sql_cmd_uninstall_component. Rule: MYSQL-UNINSTALL-COMPONENT-001.
 * The statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/uninstall-component.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class UninstallComponent implements Statement
{
    use Snapshot;

    /**
     * @var list<Text> The component URNs in written order; at least one
     */
    public readonly array $components;

    /**
     * @param list<Text> $components The component URNs in written order; at least one
     * @throws InvalidConstruction When no component is named
     */
    public function __construct(array $components)
    {
        $this->components = Check::listOf($components, Text::class, 'UNINSTALL COMPONENT names at least one component.', 1);
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('UNINSTALL', 'COMPONENT')->list($this->components);
    }
}
