<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Relation;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Partition;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds ATTACH PARTITION and DETACH PARTITION with their bound specifications.
 * @visibility SqlSemantics
 */
final class PartitionActions
{
    /**
     * Index partitions attach without a bound; table partitions attach with one or detach with a mode.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $command, Scope $scope, QueryContext $context): RelationAction
    {
        $words = ObjectAddresses::words($command);
        $partition = ObjectAddresses::name(Tree::child($command, ['qualified_name']) ?? throw new UnclassifiedSql('A partition command requires its partition.'), $context, 3);
        if ($command->name === 'index_partition_cmd') {
            return new Partition\AttachIndexPartition($partition);
        }
        if ($words[0] === 'DETACH') {
            $concurrently = Tree::child($command, ['opt_concurrently']);
            $mode = $concurrently !== null && Tree::hasTokens($concurrently) ? Partition\PartitionDetachMode::Concurrently : (in_array('FINALIZE', $words, true) ? Partition\PartitionDetachMode::Finalize : Partition\PartitionDetachMode::Immediate);
            return new Partition\DetachPartition($partition, $mode);
        }
        return new Partition\AttachPartition($partition, self::bound(Tree::child($command, ['PartitionBoundSpec']) ?? throw new UnclassifiedSql('ATTACH PARTITION requires its bound.'), $scope));
    }

    /**
     * Classifies the four bound forms.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bound(Node $spec, Scope $scope): Partition\HashPartitionBound|Partition\ListPartitionBound|Partition\RangePartitionBound|Partition\DefaultPartitionBound
    {
        $words = ObjectAddresses::words($spec);
        try {
            return match ($words[0] . ' ' . ($words[2] ?? '')) {
                'DEFAULT ' => new Partition\DefaultPartitionBound(),
                'FOR WITH' => self::hash($spec),
                'FOR IN' => new Partition\ListPartitionBound(Collections::nonEmpty(self::expressions(Tree::child($spec, ['expr_list']) ?? throw new UnclassifiedSql('IN requires its values.'), $scope))),
                'FOR FROM' => self::range($spec, $scope),
                default => throw new UnclassifiedSql('Unclassified partition bound: ' . Tree::text($spec)),
            };
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::PartitionBound, $spec, $error);
        }
    }

    /**
     * A hash bound names its modulus and remainder exactly once each.
     * @throws InvalidSql
     * @throws InvalidStructure
     */
    public static function hash(Node $spec): Partition\HashPartitionBound
    {
        $values = [];
        foreach (Tree::outer($spec, ['hash_partbound_elem']) as $element) {
            $tokens = $element->tokens();
            $key = strtolower($tokens[0]->text);
            if (!in_array($key, ['modulus', 'remainder'], true) || isset($values[$key]) || strlen($tokens[1]->text) > 9) {
                throw new InvalidSql(InputViolation::PartitionBound, $element);
            }
            $values[$key] = (int) str_replace('_', '', $tokens[1]->text);
        }
        if (!isset($values['modulus'], $values['remainder'])) {
            throw new InvalidSql(InputViolation::PartitionBound, $spec);
        }
        return new Partition\HashPartitionBound($values['modulus'], $values['remainder']);
    }

    /**
     * Range ends are expressions or the unbounded markers MINVALUE and MAXVALUE.
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function range(Node $spec, Scope $scope): Partition\RangePartitionBound
    {
        $lists = Tree::outer($spec, ['expr_list']);
        if (count($lists) !== 2) {
            throw new UnclassifiedSql('A range bound requires its two ends.');
        }
        $end = static function (Node $list) use ($scope): array {
            $values = [];
            foreach (Tree::outer($list, ['a_expr']) as $value) {
                $tokens = $value->tokens();
                $marker = count($tokens) === 1 && !str_starts_with($tokens[0]->text, '"') ? Partition\RangeBoundary::tryFrom(strtoupper($tokens[0]->text)) : null;
                $values[] = $marker ?? (new ExpressionBinder())->bind($value, $scope);
            }
            return Collections::nonEmpty($values);
        };
        return new Partition\RangePartitionBound($end($lists[0]), $end($lists[1]));
    }

    /**
     * @return list<Expression>
     */
    public static function expressions(Node $list, Scope $scope): array
    {
        return array_map(static fn (Node $value): Expression => (new ExpressionBinder())->bind($value, $scope), Tree::outer($list, ['a_expr']));
    }
}
