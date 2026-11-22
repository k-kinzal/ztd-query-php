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
 * `INSTALL COMPONENT 'urn', … [SET assignment, …]`: a request to load server components (MySQL 8.0 and later).
 *
 * Mirrors PT_install_component (Sql_cmd_install_component). Rule:
 * MYSQL-INSTALL-COMPONENT-001. The SET list (MySQL 8.0.33 and later) gives
 * component variables their values as the components are loaded; each value
 * is derived where no relation is visible, and each variable is a system
 * variable whose facts name the server state it depends on. The statement
 * returns no rows.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-component.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class InstallComponent implements Statement
{
    use Snapshot;

    /**
     * @var list<Text> The component URNs in written order; at least one
     */
    public readonly array $components;

    /**
     * @var list<ComponentSetting> The variable assignments in written order
     */
    public readonly array $settings;

    /**
     * @param list<Text> $components The component URNs in written order; at least one
     * @param list<ComponentSetting> $settings The variable assignments in written order
     * @throws InvalidConstruction When no component is named
     */
    public function __construct(array $components, array $settings = [])
    {
        $this->components = Check::listOf($components, Text::class, 'INSTALL COMPONENT names at least one component.', 1);
        $this->settings = Check::listOf($settings, ComponentSetting::class, 'INSTALL COMPONENT takes a list of variable assignments.');
    }

    /**
     * Derives each variable and value where no relation is visible.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->settings as $setting) {
            $derivation->scalar($setting->variable, $derivation->environment());
            $derivation->scalar($setting->value, $derivation->environment());
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSTALL', 'COMPONENT')->list($this->components);
        if ($this->settings !== []) {
            $out->keyword('SET')->list($this->settings);
        }
    }
}
