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
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Partition\PartitionStrategy;

/**
 * Binds MySQL PARTITION BY clauses; partitioning expressions bind against the partitioned table.
 * @visibility SqlSemantics
 */
final class PartitionSchemes
{
    /**
     * Returns the first partitioning clause below a statement, or null when it has none.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function read(Node $source, Scope $scope): ?Partition\TablePartitioning
    {
        $clause = Tree::outer($source, ['partition_clause', 'partition'])[0] ?? null;
        return $clause === null ? null : self::bind($clause, $scope);
    }

    /**
     * Binds BY function [PARTITIONS n] [SUBPARTITION BY ...] [(definitions)].
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Node $clause, Scope $scope): Partition\TablePartitioning
    {
        $type = Tree::child($clause, ['part_type_def']) ?? throw new UnclassifiedSql('PARTITION BY requires its function.');
        $count = Tree::child($clause, ['opt_num_parts']);
        $sub = Tree::child($clause, ['opt_sub_part']);
        $subCount = $sub === null ? null : Tree::child($sub, ['opt_num_subparts']);
        $function = self::function($type, $scope);
        $subpartitioning = $sub === null ? null : self::function($sub, $scope);
        $partitions = PartitionDefinitions::read($clause, $scope);
        return PartitionDefinitions::build(static fn (): Partition\TablePartitioning => new Partition\TablePartitioning(
            $function,
            $count === null ? null : MySqlNumbers::read($count, InputViolation::PartitionDefinition, true),
            $subpartitioning instanceof Partition\HashPartitioning || $subpartitioning instanceof Partition\KeyPartitioning ? $subpartitioning : null,
            $subCount === null ? null : MySqlNumbers::read($subCount, InputViolation::PartitionDefinition, true),
            $partitions,
        ), $clause);
    }

    /**
     * Binds a HASH, KEY, RANGE, LIST, or COLUMNS function from its keywords.
     * @throws InvalidSql
     */
    public static function function(Node $type, Scope $scope): Partition\PartitionFunction
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $type->tokens());
        $linear = in_array('LINEAR', $words, true);
        $algorithm = Tree::outer($type, ['opt_key_algo'])[0] ?? null;
        $columns = array_map(static fn (Node $ident): string => $scope->identifiers->name($ident->tokens()[0]), array_values(array_filter(Tree::outer($type, ['ident']), Tree::hasTokens(...))));
        $expression = Tree::outer($type, ['bit_expr'])[0] ?? null;
        $bound = $expression === null ? null : (new ExpressionBinder())->bind($expression, $scope);
        return PartitionDefinitions::build(static fn (): Partition\PartitionFunction => match (true) {
            in_array('KEY', $words, true) => new Partition\KeyPartitioning($columns, $linear, $algorithm === null || !Tree::hasTokens($algorithm) ? null : MySqlNumbers::read($algorithm, InputViolation::PartitionDefinition, true)),
            in_array('HASH', $words, true) => new Partition\HashPartitioning($bound ?? throw new UnclassifiedSql('HASH partitioning requires its expression.'), $linear),
            in_array('COLUMNS', $words, true) || in_array('FIELDS', $words, true) => new Partition\ColumnsPartitioning(self::strategy($words), \SqlSemantics\Model\Validation\Collections::nonEmpty($columns)),
            default => new Partition\ExpressionPartitioning(self::strategy($words), $bound ?? throw new UnclassifiedSql('RANGE and LIST partitioning require an expression.')),
        }, $type);
    }

    /**
     * Reads RANGE or LIST.
     * @param list<string> $words
     */
    public static function strategy(array $words): PartitionStrategy
    {
        return in_array('RANGE', $words, true) ? PartitionStrategy::Range : PartitionStrategy::List;
    }
}
