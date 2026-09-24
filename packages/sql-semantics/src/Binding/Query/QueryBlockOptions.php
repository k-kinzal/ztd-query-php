<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Optimization\SelectOption;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the MySQL query block options written after SELECT and diagnoses the ones the server takes only in the
 * first query block of the outermost query.
 *
 * @visibility SqlSemantics
 */
final class QueryBlockOptions
{
    /**
     * Returns each option once, in written order; the server accepts a repeated option but not both query cache
     * options.
     * @return list<SelectOption>
     * @throws InvalidSql
     */
    public static function bind(Node $body, QueryContext $context): array
    {
        $node = QueryNodes::local($body, ['select_options'])[0] ?? null;
        if ($node === null || $context->tables->identifiers->dialect !== \SqlSemantics\Dialect::MySql) {
            return [];
        }
        $options = [];
        foreach ($node->tokens() as $token) {
            $option = SelectOption::tryFrom(strtoupper($token->text));
            if ($option !== null && !in_array($option, $options, true)) {
                $options[] = $option;
            }
        }
        if (in_array(SelectOption::Cache, $options, true) && in_array(SelectOption::NoCache, $options, true)) {
            throw new InvalidSql(InputViolation::QueryBlockOption, $node);
        }
        return $options;
    }

    /**
     * Rejects a nested query, or a set operand after the first, that asks for an option of the outermost query's
     * first block.
     * @throws InvalidSql
     */
    public static function nested(BoundQuery $query, Node $source, QueryContext $context): BoundQuery
    {
        $release = $context->tables->schema->grammarVersion;
        if ($query instanceof BoundSelect) {
            foreach ($query->options as $option) {
                if ($option->firstBlockOnly($release)) {
                    throw new InvalidSql(InputViolation::QueryBlockOption, $source);
                }
            }
        }
        if ($query instanceof CompoundStatement) {
            self::nested($query->left, $source, $context);
            self::nested($query->right, $source, $context);
        }
        return $query;
    }
}
