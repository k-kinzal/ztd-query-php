<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use InvalidArgumentException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

final class GenerationPlans
{
    /**
     * @return GenerationPlan<true>
     */
    public static function quotedIdentifier(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('quoted_identifier', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function stringLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('string_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function integerLiteral(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('integer_literal', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function decimalLiteral(int $precision, int $scale): GenerationPlan
    {
        return GenerationPlan::lexical('decimal_literal', compact('precision', 'scale'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function multiDmlStatement(int $firstChoice, int $secondChoice): GenerationPlan
    {
        $dml = [
            ProductionPattern::containing('insert_cmd'),
            ProductionPattern::containing('UPDATE'),
            ProductionPattern::containing('DELETE'),
        ];
        $first = $dml[$firstChoice] ?? throw new InvalidArgumentException('Unknown first DML choice.');
        $second = $dml[$secondChoice] ?? throw new InvalidArgumentException('Unknown second DML choice.');

        return GenerationPlan::constrained('input', [
            'cmdlist' => [
                ProductionPattern::exactly('cmdlist', 'ecmd'),
                ProductionPattern::exactly('ecmd'),
            ],
            'ecmd' => [
                ProductionPattern::exactly('cmdx', 'SEMI'),
                ProductionPattern::exactly('cmdx', 'SEMI'),
            ],
            'cmd' => [$first, $second],
            'insert_cmd' => [
                ProductionPattern::containing('INSERT'),
                ProductionPattern::containing('INSERT'),
            ],
            'with' => [
                ProductionPattern::exactly(),
                ProductionPattern::exactly(),
            ],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function fullTextSearchStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('select', [
            'select' => [ProductionPattern::exactly('selectnowith')],
            'oneselect' => [ProductionPattern::containing('SELECT', 'selcollist', 'from', 'where_opt')],
            'selcollist' => [ProductionPattern::exactly('sclp', 'scanpt', 'STAR')],
            'sclp' => [ProductionPattern::exactly()],
            'from' => [ProductionPattern::nonEmpty()],
            'seltablist' => [ProductionPattern::exactly('stl_prefix', 'nm', 'dbnm', 'as', 'on_using')],
            'stl_prefix' => [ProductionPattern::exactly()],
            'on_using' => [ProductionPattern::exactly()],
            'where_opt' => [ProductionPattern::nonEmpty()],
            'expr' => [
                ProductionPattern::exactly('expr', 'likeop', 'expr'),
                ProductionPattern::exactly('term'),
                ProductionPattern::exactly('term'),
            ],
            'likeop' => [ProductionPattern::exactly('MATCH')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function insertFunctionUpsertStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('insert_cmd', 'select', 'upsert')],
            'with' => [ProductionPattern::exactly()],
            'insert_cmd' => [ProductionPattern::containing('INSERT')],
            'select' => [ProductionPattern::exactly('selectnowith')],
            'selectnowith' => [ProductionPattern::exactly('oneselect')],
            'oneselect' => [ProductionPattern::exactly('values')],
            'values' => [ProductionPattern::exactly('VALUES', 'LP', 'nexprlist', 'RP')],
            'nexprlist' => [ProductionPattern::exactly('expr')],
            'upsert' => [
                ProductionPattern::exactly('ON', 'CONFLICT', 'DO', 'UPDATE', 'SET', 'setlist', 'where_opt', 'returning'),
            ],
            'expr' => [
                ProductionPattern::exactly('term'),
                ProductionPattern::containing('idj', 'LP', 'RP'),
            ],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function temporaryTableStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
            'temp' => [ProductionPattern::containing('TEMP')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function viewStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('createkw', 'VIEW', 'select')],
            'oneselect' => [ProductionPattern::containing('SELECT')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function generatedColumnStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
            'create_table_args' => [ProductionPattern::containing('columnlist')],
            'carglist' => [
                ProductionPattern::exactly('carglist', 'ccons'),
                ProductionPattern::exactly(),
            ],
            'ccons' => [ProductionPattern::containing('GENERATED', 'generated')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function foreignKeyCascadeStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
            'create_table_args' => [ProductionPattern::containing('columnlist', 'conslist_opt')],
            'conslist_opt' => [ProductionPattern::nonEmpty()],
            'tcons' => [ProductionPattern::containing('FOREIGN', 'KEY', 'REFERENCES')],
            'refargs' => [
                ProductionPattern::exactly('refargs', 'refarg'),
                ProductionPattern::exactly('refargs', 'refarg'),
                ProductionPattern::exactly(),
            ],
            'refarg' => [
                ProductionPattern::containing('ON', 'DELETE'),
                ProductionPattern::containing('ON', 'UPDATE'),
            ],
            'refact' => [
                ProductionPattern::exactly('CASCADE'),
                ProductionPattern::exactly('CASCADE'),
            ],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function foreignKeyConstraint(): GenerationPlan
    {
        return GenerationPlan::constrained('conslist', [
            'conslist' => [
                ProductionPattern::exactly('conslist', 'tconscomma', 'tcons'),
                ProductionPattern::exactly('tcons'),
            ],
            'tcons' => [
                ProductionPattern::containing('CONSTRAINT'),
                ProductionPattern::containing('FOREIGN', 'KEY'),
            ],
            'tconscomma' => [ProductionPattern::exactly()],
            'eidlist_opt' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * Directs a bounded walk that grows one statement from its own rule.
     *
     * @param non-empty-string $startRule Rule the statement is grown from
     * @param int $maxDepth How deep the walk may recurse
     *
     * @return GenerationPlan<false> Plan for one bounded statement
     */
    public static function statement(string $startRule, int $maxDepth): GenerationPlan
    {
        return GenerationPlan::fromRule($startRule)->withMaxDepth($maxDepth);
    }
}
