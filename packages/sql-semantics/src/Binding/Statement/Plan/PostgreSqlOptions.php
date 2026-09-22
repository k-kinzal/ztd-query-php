<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Plan;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Plan;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Classifies PostgreSQL's EXPLAIN option names and finite argument domains.
 * @visibility SqlSemantics
 */
final class PostgreSqlOptions
{
    /**
     * @throws InvalidSql
     */
    public static function read(Node $source, Identifiers $identifiers): Plan\PostgreSqlPlan
    {
        $options = [];
        foreach (Tree::outer($source, ['utility_option_elem']) as $option) {
            $name = Tree::child($option, ['utility_option_name']);
            $argument = Tree::child($option, ['utility_option_arg']);
            if ($name === null) {
                Tree::invalid($option, 'EXPLAIN option name');
            }
            $key = strtolower($identifiers->name($name->tokens()[0]));
            $value = $argument === null ? null : strtoupper($identifiers->name($argument->tokens()[0]));
            if ($key === 'format') {
                $options['format'] = Plan\PostgreSqlFormat::tryFrom($value ?? '') ?? throw new InvalidSql(InputViolation::ExplainSetting, $option);
            } elseif ($key === 'serialize') {
                $options['serialization'] = Plan\SerializationCost::tryFrom($value ?? 'TEXT') ?? throw new InvalidSql(InputViolation::ExplainSetting, $option);
            } else {
                $property = self::booleanOption($key, $option);
                $options[$property] = self::boolean($value, $option);
            }
        }
        if ($options === []) {
            $options['analyze'] = Tree::child($source, ['analyze_keyword']) !== null;
            $options['verbose'] = Tree::child($source, ['opt_verbose']) !== null;
        }
        try {
            return new Plan\PostgreSqlPlan(...$options);
        } catch (\SqlSemantics\Model\Validation\InvalidStructure $error) {
            throw new InvalidSql(InputViolation::ExplainCombination, $source, $error);
        }
    }

    /**
     * @throws InvalidSql
     */
    public static function boolean(?string $word, Node $source): bool
    {
        if ($word === null || $word === '1') {
            return true;
        }
        if ($word === '0') {
            return false;
        }
        $true = $word !== '' && (str_starts_with('TRUE', $word) || str_starts_with('YES', $word) || str_starts_with('ON', $word));
        $false = $word !== '' && (str_starts_with('FALSE', $word) || str_starts_with('NO', $word) || str_starts_with('OFF', $word));
        if ($true === $false) {
            throw new InvalidSql(InputViolation::ExplainSetting, $source);
        }
        return $true;
    }

    /**
     * Maps a recognized SQL flag to its typed plan property.
     * @return 'analyze'|'verbose'|'costs'|'settings'|'genericPlan'|'buffers'|'wal'|'timing'|'summary'|'memory'
     * @throws InvalidSql
     */
    public static function booleanOption(string $key, Node $option): string
    {
        return match ($key) {
            'analyze', 'analyse' => 'analyze', 'verbose' => 'verbose', 'costs' => 'costs', 'settings' => 'settings',
            'generic_plan' => 'genericPlan', 'buffers' => 'buffers', 'wal' => 'wal', 'timing' => 'timing',
            'summary' => 'summary', 'memory' => 'memory',
            default => throw new InvalidSql(InputViolation::ExplainOption, $option),
        };
    }
}
