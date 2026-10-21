<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;

/**
 * Derives the facts of LOAD DATA and LOAD XML.
 *
 * Rule: MYSQL-LOAD-001. The table is resolved like the table of INSERT
 * (MYSQL-DML-TARGET-001) and is the one visible relation of the column
 * list and of the SET assignments. Input user variables introduce string
 * reads before SET expressions are resolved. Existing entries retain their
 * assignment collation during preparation from 5.7; absent entries and 5.6 use
 * the file charset.
 * A SET value may refer to columns and user variables; the
 * value DEFAULT is the default of its column. The file is read by the
 * server or the client and is not examined. The statement returns no rows.
 * Terminates: one pass over the finite lists. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/load-data.html,
 * https://dev.mysql.com/doc/refman/8.4/en/load-xml.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LoadFacts
{
    /**
     * Derives the table, the columns and the assignments.
     */
    public function derive(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments|LoadTable $load, Derivation $derivation): void
    {
        $conflict = in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql8044, \SqlSemantics\Contract\GrammarRelease::MySql810], true) ? null : $this->algorithm($load);
        if ($conflict !== null) {
            $derivation->report($conflict);
        }
        $base = $derivation->environment();
        if ($load->charset?->name !== null) {
            if (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($load->charset->name->value) === null) {
                $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset($load->charset->name->value));
            }
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::charset($load->charset->name->value, $derivation);
        }
        $fact = $derivation->relation($load->table, $base);
        $environment = new Environment($derivation->context, $base, [new VisibleRelation($load->table, $fact->shape, null, $load->table->name, [], (new TableShapes())->implicit($fact))]);
        $this->variables($load, $derivation);
        foreach ($load->columns as $column) {
            $derivation->scalar($column, $column instanceof ColumnUse ? (new WriteScope())->written($environment) : $base);
        }
        (new WriteScope())->assign($load->assignments, $derivation, $environment, $environment, false);
    }

    /**
     * Resolves file-input variables before the SET expressions that read them.
     * These occurrences read strings even if an entry previously held a number.
     * Their prepared collation is the preceding assignment collation for existing
     * entries and the file charset for new entries. Loading a record updates the
     * runtime entry separately. MySQL 5.6 resolves all these reads in the file
     * charset. This derivation does not assign a session value.
     */
    public function variables(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments|LoadTable $load, Derivation $derivation): void
    {
        $settings = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context);
        $charset = $load->charset?->name === null ? null : \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($load->charset->name->value);
        $collation = $charset?->defaultCollation($derivation->context->profile->grammar) ?? $settings->databaseCollation ?? $settings->server ?? $settings->connection;
        $variables = new \SqlSemantics\Platform\MySql\Rules\Typing\Variables($settings, $derivation->context->profile->grammar);
        foreach ($load->columns as $column) {
            if ($column instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable) {
                $name = strtolower($column->name->value);
                $entry = $settings->userVariables[$name] ?? null;
                $prepared = match (true) {
                    $derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 => $collation,
                    $entry instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry => $entry->collation,
                    $entry instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain => $entry->kind->numeric() ? $settings->connection : $entry->collation,
                    default => $collation,
                };
                $derivation->introducedVariables[$name] = new \SqlSemantics\Statement\Type\Known($variables->text($prepared));
            }
        }
    }

    /**
     * Resolves option compatibility independently of engine and file availability.
     */
    public function algorithm(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\RefusedLoadAssignments|LoadTable $load): ?LoadConflict
    {
        return (new LoadOptions())->conflict($load->format, $load->input, $load->duplicates, $load->compression, $load->columns !== [], $load->assignments !== [], $load->bulk, $load->lines);
    }
}
