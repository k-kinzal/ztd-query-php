<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

final class GenerationPlans
{
    /** @return GenerationPlan<true> */
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

    /** @return GenerationPlan<true> */
    public static function temporaryTableStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('create_table', 'create_table_args')],
            'temp' => [ProductionPattern::containing('TEMP')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function viewStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('cmd', [
            'cmd' => [ProductionPattern::containing('createkw', 'VIEW', 'select')],
            'oneselect' => [ProductionPattern::containing('SELECT')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
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
}
