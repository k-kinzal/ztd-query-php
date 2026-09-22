<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Plan;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Plan;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the wrapped operation in the same schema without evaluating the plan.
 * @visibility SqlSemantics
 */
final class ExplainBinder
{
    /**
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): ?BoundStatement
    {
        $word = strtoupper($source->tokens()[0]->text ?? '');
        if (!in_array($word, ['EXPLAIN', 'DESCRIBE', 'DESC'], true)) {
            return null;
        }
        if ($origin->dialect === Dialect::Sqlite) {
            $command = Tree::outer($source, ['cmd'])[0] ?? throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Missing EXPLAIN command.');
            $explain = Tree::child($source, ['explain']);
            $options = $explain !== null && count($explain->tokens()) === 3 ? Plan\SqlitePlan::QueryPlan : Plan\SqlitePlan::Bytecode;
        } else {
            $command = Tree::child($source, ['ExplainableStmt', 'explainable_stmt', 'explanable_command']);
            if ($command === null) {
                return null;
            }
            $options = $origin->dialect === Dialect::PostgreSql ? PostgreSqlOptions::read($source, $context->tables->identifiers) : self::mysql($source, $context);
            if ($options instanceof Plan\MySqlPlan && strtoupper($command->tokens()[0]->text ?? '') === 'FOR') {
                $number = Tree::child($command, ['real_ulong_num']);
                if ($number === null) {
                    Tree::invalid($command, 'connection number');
                }
                return new ExplainConnectionStatement($origin, new \SqlSemantics\Type\Identity\Numeric\NumericParameter(Tree::text($number)), $options->format);
            }
        }
        $bound = (new StatementBinder($context->tables))->node($command, $command, $context);
        return new ExplainStatement($origin, $bound, $options);
    }

    /**
     * @throws InvalidSql
     */
    public static function mysql(Node $source, QueryContext $context): Plan\MySqlPlan
    {
        $options = Tree::child($source, ['opt_explain_options', 'opt_extended_describe']);
        $format = $options === null ? null : Tree::outer($options, ['opt_explain_format', 'explain_format'])[0] ?? null;
        $formatName = $format === null ? '' : strtoupper($context->tables->identifiers->name($format->tokens()[count($format->tokens()) - 1]));
        $words = $options === null ? [] : array_map(static fn ($token): string => strtoupper($token->text), $options->tokens());
        try {
            return new Plan\MySqlPlan(Plan\MySqlFormat::tryFrom($formatName) ?? throw new InvalidSql(InputViolation::ExplainSetting, $source), in_array('ANALYZE', $words, true), in_array('EXTENDED', $words, true), in_array('PARTITIONS', $words, true));
        } catch (\SqlSemantics\Model\Validation\InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ExplainCombination, $source, $error);
        }
    }
}
