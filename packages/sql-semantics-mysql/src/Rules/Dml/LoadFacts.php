<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;

/**
 * Derives the facts of LOAD DATA and LOAD XML.
 *
 * Rule: MYSQL-LOAD-001. The table is resolved like the table of INSERT
 * (MYSQL-DML-TARGET-001) and is the one visible relation of the column
 * list and of the SET assignments; a user variable of the column list is a
 * session value. A SET value may refer to columns and user variables; the
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
    public function derive(LoadTable $load, Derivation $derivation): void
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
        foreach ($load->columns as $column) {
            $derivation->scalar($column, $column instanceof ColumnUse ? (new WriteScope())->written($environment) : $base);
        }
        (new WriteScope())->assign($load->assignments, $derivation, $environment, $environment, false);
    }

    /**
     * Resolves option compatibility independently of engine and file availability.
     */
    public function algorithm(LoadTable $load): ?LoadConflict
    {
        $bulk = $load->bulk->bulk ?? false;
        $rule = match (true) {
            $bulk && $load->format === LoadFormat::Xml => LoadRule::XmlBulk,
            $bulk && $load->input->local => LoadRule::LocalBulk,
            $bulk && $load->duplicates === DuplicateHandling::Ignore => LoadRule::IgnoreBulk,
            !$bulk && $load->input->count !== null => LoadRule::MultipleFilesWithoutBulk,
            !$bulk && $load->input->source === LoadSource::Url => LoadRule::UrlWithoutBulk,
            !$bulk && $load->compression !== null => LoadRule::CompressionWithoutBulk,
            default => null,
        };

        return $rule === null ? null : new LoadConflict($rule);
    }
}
