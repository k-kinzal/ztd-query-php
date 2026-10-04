<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Plugin;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `UNINSTALL COMPONENT 'urn', …`: a request to unload server components (MySQL 8.0 and later).
 *
 * Mirrors Sql_cmd_uninstall_component. Rule: MYSQL-UNINSTALL-COMPONENT-001.
 * The statement names no relation and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/uninstall-component.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a component
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("uninstall component 'file://a'")->toString() // => "UNINSTALL COMPONENT 'file://a'"
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
