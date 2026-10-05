<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\AlterResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CpuRange;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\DropResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\SetResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the resource group statements (8.0+).
 *
 * Rule: MYSQL-ACCOUNT-RESOURCE-GROUP-001. Scope: create_resource_group_stmt,
 * alter_resource_group_stmt, drop_resource_group_stmt,
 * set_resource_group_stmt, resource_group_types,
 * opt_resource_group_vcpu_list, vcpu_range_spec_list, vcpu_num_or_range,
 * opt_resource_group_priority, signed_num,
 * opt_resource_group_enable_disable, opt_force, thread_id_list,
 * thread_id_list_options. Numbers keep their exact text; the optional `=`
 * and commas carry no meaning. Constructs: CreateResourceGroup,
 * AlterResourceGroup, DropResourceGroup, SetResourceGroup, CpuRange,
 * ThreadPriority. Terminates: the lists are flattened iteratively; every
 * other child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/resource-groups.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ResourceGroupRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a resource group statement.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function statement(Form $form): Statement
    {
        $names = $this->lowering->names;

        return match ($form->signature) {
            'create_resource_group_stmt: CREATE RESOURCE_SYM GROUP_SYM ident TYPE_SYM opt_equal resource_group_types opt_resource_group_vcpu_list opt_resource_group_priority opt_resource_group_enable_disable' => $this->create($form),
            'alter_resource_group_stmt: ALTER RESOURCE_SYM GROUP_SYM ident opt_resource_group_vcpu_list opt_resource_group_priority opt_resource_group_enable_disable opt_force' => new AlterResourceGroup(
                $names->identifier($form->node(3)),
                $this->cpus($form->node(4)),
                $this->priority($form->node(5)),
                $this->enabled($form->node(6)),
                $this->force($form->node(7)),
            ),
            'drop_resource_group_stmt: DROP RESOURCE_SYM GROUP_SYM ident opt_force' => new DropResourceGroup($names->identifier($form->node(3)), $this->force($form->node(4))),
            'set_resource_group_stmt: SET_SYM RESOURCE_SYM GROUP_SYM ident' => new SetResourceGroup($names->identifier($form->node(3))),
            'set_resource_group_stmt: SET_SYM RESOURCE_SYM GROUP_SYM ident FOR_SYM thread_id_list_options' => new SetResourceGroup($names->identifier($form->node(3)), $this->threads($form->node(5))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CREATE RESOURCE GROUP.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Form $form): CreateResourceGroup
    {
        $name = $this->lowering->names->identifier($form->node(3));
        $this->lowering->options->present($form->node(5));
        $type = $this->lowering->form($form->node(6));
        $kind = match ($type->signature) {
            'resource_group_types: USER' => ResourceGroupKind::User,
            'resource_group_types: SYSTEM_SYM' => ResourceGroupKind::System,
            default => throw ImplementationGap::production($type),
        };

        return new CreateResourceGroup($name, $kind, $this->cpus($form->node(7)), $this->priority($form->node(8)), $this->enabled($form->node(9)));
    }

    /**
     * Lowers an opt_resource_group_vcpu_list; an absent option is empty.
     *
     * @return list<CpuRange>
     * @throws ImplementationGap When a production has no rule
     */
    public function cpus(Node $option): array
    {
        $form = $this->lowering->form($option);
        if ($form->signature === 'opt_resource_group_vcpu_list:') {
            return [];
        }
        if ($form->signature !== 'opt_resource_group_vcpu_list: VCPU_SYM opt_equal vcpu_range_spec_list') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->present($form->node(1));
        $list = $form->node(2);
        $this->claimed($list, ['vcpu_range_spec_list: vcpu_num_or_range', 'vcpu_range_spec_list: vcpu_range_spec_list opt_comma vcpu_num_or_range']);
        $ranges = [];
        foreach ((new Lists())->items($list) as $item) {
            if ($item->name === 'opt_comma') {
                $this->lowering->options->present($item);
                continue;
            }
            $ranges[] = $this->range($item);
        }

        return $ranges;
    }

    /**
     * Lowers a vcpu_num_or_range.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function range(Node $range): CpuRange
    {
        $form = $this->lowering->form($range);

        return match ($form->signature) {
            'vcpu_num_or_range: NUM' => new CpuRange($this->number($form, 0)),
            'vcpu_num_or_range: NUM - NUM' => new CpuRange($this->number($form, 0), $this->number($form, 2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an opt_resource_group_priority; an absent option is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function priority(Node $option): ?ThreadPriority
    {
        $form = $this->lowering->form($option);
        if ($form->signature === 'opt_resource_group_priority:') {
            return null;
        }
        if ($form->signature !== 'opt_resource_group_priority: THREAD_PRIORITY_SYM opt_equal signed_num') {
            throw ImplementationGap::production($form);
        }
        $this->lowering->options->present($form->node(1));
        $signed = $this->lowering->form($form->node(2));

        return match ($signed->signature) {
            'signed_num: NUM' => new ThreadPriority(false, $this->number($signed, 0)),
            'signed_num: - NUM' => new ThreadPriority(true, $this->number($signed, 1)),
            default => throw ImplementationGap::production($signed),
        };
    }

    /**
     * Lowers an opt_resource_group_enable_disable: true for ENABLE, false for DISABLE, null when absent.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function enabled(Node $option): ?bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_resource_group_enable_disable:' => null,
            'opt_resource_group_enable_disable: ENABLE_SYM' => true,
            'opt_resource_group_enable_disable: DISABLE_SYM' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an opt_force.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function force(Node $option): bool
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'opt_force:' => false,
            'opt_force: FORCE_SYM' => true,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the thread ids of SET RESOURCE GROUP … FOR.
     *
     * @return list<Numeral>
     * @throws ImplementationGap When a production has no rule
     */
    public function threads(Node $options): array
    {
        $this->claimed($options, ['thread_id_list_options: thread_id_list']);
        $list = $this->lowering->form($options)->node(0);
        $this->claimed($list, ['thread_id_list: real_ulong_num', 'thread_id_list: thread_id_list opt_comma real_ulong_num']);
        $threads = [];
        foreach ((new Lists())->items($list) as $item) {
            if ($item->name === 'opt_comma') {
                $this->lowering->options->present($item);
                continue;
            }
            $threads[] = $this->lowering->numbers->numeral($item);
        }

        return $threads;
    }

    /**
     * Lowers the number token at a position.
     */
    public function number(Form $form, int $position): Numeral
    {
        return $this->lowering->numbers->token($form->token($position));
    }

    /**
     * Confirms that a node is one of the given productions.
     *
     * @param list<string> $signatures
     * @throws ImplementationGap When the production has no rule
     */
    public function claimed(Node $node, array $signatures): void
    {
        $form = $this->lowering->form($node);
        if (!in_array($form->signature, $signatures, true)) {
            throw ImplementationGap::production($form);
        }
    }
}
