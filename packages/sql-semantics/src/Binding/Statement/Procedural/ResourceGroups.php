<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupState;
use SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Server\ResourceGroup as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds CREATE, ALTER and SET RESOURCE GROUP; SET RESOURCE GROUP never reaches the generic SET binder.
 * @visibility SqlSemantics
 */
final class ResourceGroups
{
    /**
     * Reads the group name and the options of the concrete operation; options the server rejects are diagnosed.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): BoundStatement
    {
        $name = $context->tables->identifiers->name((Tree::child($node, ['ident']) ?? Tree::invalid($node, 'resource group name'))->tokens()[0]);
        $cpus = self::cpus($node);
        $priorityNode = Tree::child(Tree::child($node, ['opt_resource_group_priority']) ?? $node, ['signed_num']);
        $priority = $priorityNode === null ? null : (int) str_replace(' ', '', Tree::text($priorityNode));
        $stateNode = Tree::child($node, ['opt_resource_group_enable_disable']);
        $state = $stateNode === null || Tree::text($stateNode) === '' ? null : ResourceGroupState::from(strtoupper(Tree::text($stateNode)));
        try {
            return match ($node->name) {
                'create_resource_group_stmt' => new Statement\CreateResourceGroupStatement($origin, $name, ThreadCategory::from(strtoupper(Tree::text(Tree::child($node, ['resource_group_types']) ?? Tree::invalid($node, 'resource group type')))), $cpus, $priority, $state ?? ResourceGroupState::Enabled),
                'alter_resource_group_stmt' => new Statement\AlterResourceGroupStatement($origin, $name, $cpus, $priority, $state, self::force($node)),
                default => new Statement\SetResourceGroupStatement($origin, $name, self::threads($node)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ResourceGroupOption, $node, $error);
        }
    }

    /**
     * Whether ALTER RESOURCE GROUP requests FORCE.
     */
    public static function force(Node $node): bool
    {
        $force = Tree::child($node, ['opt_force']);
        return $force !== null && Tree::text($force) !== '';
    }

    /**
     * Reads each VCPU number or range in request order.
     * @return list<CpuRange>
     * @throws InvalidSql
     */
    public static function cpus(Node $node): array
    {
        $ranges = [];
        foreach (Tree::outer($node, ['vcpu_num_or_range']) as $range) {
            $bounds = array_values(array_filter(array_map(static fn ($token): string => $token->text, $range->tokens()), static fn (string $text): bool => $text !== '-'));
            try {
                $ranges[] = new CpuRange((int) $bounds[0], (int) ($bounds[1] ?? $bounds[0]));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::ResourceGroupOption, $range, $error);
            }
        }
        return $ranges;
    }

    /**
     * Keeps each thread identifier as its integer literal spelling.
     * @return list<Literal>
     * @throws InvalidSql
     */
    public static function threads(Node $node): array
    {
        $threads = [];
        foreach (Tree::outer($node, ['real_ulong_num']) as $thread) {
            $literal = (new LiteralBinder(Dialect::MySql))->bind($thread->tokens()[0] ?? Tree::invalid($thread, 'thread identifier'));
            $threads[] = $literal instanceof Literal ? $literal : throw new InvalidSql(InputViolation::ResourceGroupOption, $thread);
        }
        return $threads;
    }
}
