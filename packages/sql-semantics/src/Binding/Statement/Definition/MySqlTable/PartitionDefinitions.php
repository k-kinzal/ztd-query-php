<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\MySqlTable;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Partition;
use SqlSemantics\Model\Definition\Relation\Partition\RangeBoundary;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds MySQL partition and subpartition definitions; partition values are constants bound without a table.
 * @visibility SqlSemantics
 */
final class PartitionDefinitions
{
    /**
     * Binds every partition definition below a node, in SQL order.
     * @return list<Partition\PartitionDefinition>
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $source, Scope $scope): array
    {
        return array_map(static fn (Node $definition): Partition\PartitionDefinition => self::definition($definition, $scope), Tree::outer($source, ['part_definition']));
    }

    /**
     * Binds one PARTITION name [VALUES ...] [options] [(SUBPARTITION ...)] definition.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function definition(Node $definition, Scope $scope): Partition\PartitionDefinition
    {
        $name = Tree::child($definition, ['ident', 'part_name']) ?? throw new UnclassifiedSql('A partition definition requires its name.');
        $values = Tree::child($definition, ['opt_part_values']);
        $options = Tree::child($definition, ['opt_part_options']);
        $subpartitions = [];
        foreach (Tree::outer($definition, ['sub_part_definition']) as $subpartition) {
            $subName = Tree::child($subpartition, ['ident_or_text', 'sub_name']) ?? throw new UnclassifiedSql('A subpartition requires its name.');
            $subOptions = Tree::child($subpartition, ['opt_part_options']);
            $subpartitions[] = self::build(static fn (): Partition\SubpartitionDefinition => new Partition\SubpartitionDefinition(TableOptions::text($subName, $scope->identifiers), $subOptions === null ? new Partition\PartitionProperties() : self::properties($subOptions, $scope)), $subpartition);
        }
        return self::build(static fn (): Partition\PartitionDefinition => new Partition\PartitionDefinition(
            $scope->identifiers->name($name->tokens()[0]),
            $values === null ? null : self::values($values, new Scope($scope->identifiers, queries: $scope->queries)),
            $options === null ? new Partition\PartitionProperties() : self::properties($options, $scope),
            $subpartitions,
        ), $definition);
    }

    /**
     * Binds VALUES LESS THAN or VALUES IN.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function values(Node $values, Scope $scope): Partition\RangeBound|Partition\ListBound
    {
        [$lists, $items] = $values->find('part_value_expr_item') !== [] ? ['part_value_item', 'part_value_expr_item'] : ['part_value_item_list_paren', 'part_value_item'];
        $in = Tree::child($values, ['part_values_in']);
        if ($in === null) {
            $bound = Tree::child($values, ['part_func_max']) ?? throw new UnclassifiedSql('VALUES LESS THAN requires its bound.');
            $entries = Tree::outer($bound, [$items]);
            return self::build(static fn (): Partition\RangeBound => new Partition\RangeBound($entries === [] ? [RangeBoundary::MaxValue] : array_map(static fn (Node $entry): Expression|RangeBoundary => self::entry($entry, $scope, true), $entries)), $bound);
        }
        $first = $in->children[0] ?? null;
        $tuples = $first instanceof Token
            ? array_map(static fn (Node $list): array => array_map(static fn (Node $entry): Expression => self::expression($entry, $scope), Tree::outer($list, [$items])), Tree::outer($in, [$lists]))
            : array_map(static fn (Node $entry): array => [self::expression($entry, $scope)], Tree::outer($in, [$items]));
        return self::build(static fn (): Partition\ListBound => new Partition\ListBound(Collections::nonEmpty(array_map(Collections::nonEmpty(...), $tuples))), $in);
    }

    /**
     * Binds one bound entry; MAXVALUE is allowed only in a range bound.
     * @throws InvalidSql
     */
    public static function entry(Node $entry, Scope $scope, bool $range): Expression|RangeBoundary
    {
        $expression = Tree::child($entry, ['bit_expr']);
        if ($expression === null) {
            return $range ? RangeBoundary::MaxValue : throw new InvalidSql(InputViolation::PartitionDefinition, $entry);
        }
        return (new ExpressionBinder())->bind($expression, $scope);
    }

    /**
     * Binds a LIST value, rejecting MAXVALUE.
     * @throws InvalidSql
     */
    public static function expression(Node $entry, Scope $scope): Expression
    {
        $value = self::entry($entry, $scope, false);
        return $value instanceof Expression ? $value : throw new InvalidSql(InputViolation::PartitionDefinition, $entry);
    }

    /**
     * Reads partition options; a later option replaces an earlier one.
     * @throws InvalidSql
     */
    public static function properties(Node $options, Scope $scope): Partition\PartitionProperties
    {
        $values = [];
        foreach (Tree::outer($options, ['part_option', 'opt_part_option']) as $option) {
            $words = array_values(array_filter(array_map(static fn (Token $token): string => strtoupper($token->text), $option->tokens()), static fn (string $word): bool => $word !== 'STORAGE'));
            $values[$words[0]] = $option->tokens()[count($option->tokens()) - 1];
        }
        $text = static fn (string $key): ?string => isset($values[$key]) ? TableOptions::text($values[$key], $scope->identifiers) : null;
        $number = static fn (string $key): ?int => isset($values[$key]) ? MySqlNumbers::read($values[$key], InputViolation::PartitionDefinition, true) : null;
        return self::build(static fn (): Partition\PartitionProperties => new Partition\PartitionProperties($text('ENGINE'), $text('COMMENT'), $text('DATA'), $text('INDEX'), $number('MAX_ROWS'), $number('MIN_ROWS'), $text('TABLESPACE'), $number('NODEGROUP')), $options);
    }

    /**
     * Turns a violated invariant of a partition or alteration operand into a diagnosis of the written SQL.
     * @template T of object
     * @param callable(): T $construct
     * @return T
     * @throws InvalidSql
     */
    public static function build(callable $construct, Node $source, InputViolation $violation = InputViolation::PartitionDefinition): object
    {
        try {
            return $construct();
        } catch (InvalidStructure $error) {
            throw new InvalidSql($violation, $source, $error);
        }
    }
}
