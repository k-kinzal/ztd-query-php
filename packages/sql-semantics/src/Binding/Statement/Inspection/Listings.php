<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Statement\Inspection\Schema;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies SHOW listings of schema objects, character sets, and collations.
 * @visibility SqlSemantics
 */
final class Listings
{
    /**
     * Routes by the keyword following SHOW and its modifiers.
     */
    public static function bind(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        return self::catalog($origin, $request, $context) ?? self::described($origin, $request, $context);
    }

    /**
     * Listings scoped to the server or to one database: synonymous keywords are normalized and the database selector folded into the operands.
     */
    public static function catalog(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        $form = $request->form;
        $database = Filters::database($form, $context);
        $build = match ($request->word(0)) {
            'DATABASES', 'SCHEMAS' => static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowDatabasesStatement => new Schema\ShowDatabasesStatement($origin, $filter),
            'TABLES' => static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowTablesStatement => new Schema\ShowTablesStatement($origin, $database, $filter, $request->full, $request->extended),
            'TABLE' => $request->word(1) === 'STATUS' ? static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowTableStatusStatement => new Schema\ShowTableStatusStatement($origin, $database, $filter) : null,
            'OPEN' => $request->word(1) === 'TABLES' ? static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowOpenTablesStatement => new Schema\ShowOpenTablesStatement($origin, $database, $filter) : null,
            'TRIGGERS' => static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowTriggersStatement => new Schema\ShowTriggersStatement($origin, $database, $filter),
            'EVENTS' => static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowEventsStatement => new Schema\ShowEventsStatement($origin, $database, $filter),
            'CHARSET', 'CHARACTER', 'CHAR' => static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowCharacterSetsStatement => new Schema\ShowCharacterSetsStatement($origin, $filter),
            'COLLATION' => static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowCollationsStatement => new Schema\ShowCollationsStatement($origin, $filter),
            default => null,
        };
        return $build === null ? null : Filters::restrict($build, $form, $context);
    }

    /**
     * Listings describing one table: the table is resolved once and an index listing accepts only a WHERE condition.
     */
    public static function described(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        $form = $request->form;
        if (!in_array($request->word(0), ['COLUMNS', 'FIELDS', 'INDEX', 'INDEXES', 'KEYS'], true)) {
            return null;
        }
        $table = Filters::table($origin, $form, $context);
        if (in_array($request->word(0), ['COLUMNS', 'FIELDS'], true)) {
            return Filters::restrict(static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowColumnsStatement => new Schema\ShowColumnsStatement($origin, $table, $filter, $request->full, $request->extended), $form, $context);
        }
        return Filters::restrict(static fn (PatternFilter|ConditionFilter|null $filter): Schema\ShowIndexesStatement => $filter instanceof PatternFilter ? Tree::invalid($form, 'index listing restriction') : new Schema\ShowIndexesStatement($origin, $table, $filter, $request->extended), $form, $context);
    }
}
