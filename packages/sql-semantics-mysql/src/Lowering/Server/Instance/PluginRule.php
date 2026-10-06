<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\OnWord;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Statement;

/**
 * Lowers INSTALL and UNINSTALL of plugins and components.
 *
 * Rule: MYSQL-PLUGIN-001. Scope: install (5.6, 5.7), install_stmt, uninstall,
 * opt_install_set_value_list, install_set_value_list, install_set_value,
 * install_option_type, install_set_rvalue (8.0 and later). The variable
 * goes through the leaf variable rule with the scope written before it; the
 * value through the expression family, or is the word ON. The equals sign
 * is the one of `equal` (LeafNoise). Constructs: InstallPlugin,
 * UninstallPlugin, InstallComponent, UninstallComponent, ComponentSetting,
 * OnWord. Terminates: the assignment list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/install-plugin.html,
 * https://dev.mysql.com/doc/refman/8.4/en/install-component.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class PluginRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a plugin or component statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $names = $this->lowering->names;
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'install: INSTALL_SYM PLUGIN_SYM ident SONAME_SYM TEXT_STRING_sys', 'install_stmt: INSTALL_SYM PLUGIN_SYM ident SONAME_SYM TEXT_STRING_sys' => new InstallPlugin(
                $names->identifier($form->node(2)),
                $literals->text($form->node(4)),
            ),
            'install_stmt: INSTALL_SYM COMPONENT_SYM TEXT_STRING_sys_list opt_install_set_value_list' => new InstallComponent($literals->texts($form->node(2)), $this->settings($form->node(3))),
            'uninstall: UNINSTALL_SYM PLUGIN_SYM ident' => new UninstallPlugin($names->identifier($form->node(2))),
            'uninstall: UNINSTALL_SYM COMPONENT_SYM TEXT_STRING_sys_list' => new UninstallComponent($literals->texts($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the SET list of INSTALL COMPONENT: a node of `opt_install_set_value_list`.
     *
     * @return list<ComponentSetting>
     * @throws ImplementationGap When a production has no rule
     */
    public function settings(Node $list): array
    {
        $form = $this->lowering->form($list);
        if ($form->signature === 'opt_install_set_value_list:') {
            return [];
        }
        if ($form->signature !== 'opt_install_set_value_list: SET_SYM install_set_value_list') {
            throw ImplementationGap::production($form);
        }
        $spine = $form->node(1);
        $this->lowering->names->claimed($this->lowering->form($spine), ['install_set_value_list: install_set_value', 'install_set_value_list: install_set_value_list , install_set_value']);
        $settings = [];
        foreach ((new Lists())->items($spine) as $item) {
            $settings[] = $this->setting($item);
        }

        return $settings;
    }

    /**
     * Lowers one assignment: a node of `install_set_value`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function setting(Node $setting): ComponentSetting
    {
        $form = $this->lowering->form($setting);
        if ($form->signature !== 'install_set_value: install_option_type lvalue_variable equal install_set_rvalue') {
            throw ImplementationGap::production($form);
        }
        $scope = $this->lowering->form($form->node(0));
        $variable = $this->lowering->variables->system($form->node(1), match ($scope->signature) {
            'install_option_type:' => null,
            'install_option_type: GLOBAL_SYM' => VariableScope::Global,
            'install_option_type: PERSIST_SYM' => VariableScope::Persist,
            default => throw ImplementationGap::production($scope),
        });
        $this->lowering->options->skip($form->node(2));
        $value = $this->lowering->form($form->node(3));

        return new ComponentSetting($variable, match ($value->signature) {
            'install_set_rvalue: expr' => $this->lowering->expressions->expression($value->node(0)),
            'install_set_rvalue: ON_SYM' => new OnWord(),
            default => throw ImplementationGap::production($value),
        });
    }
}
