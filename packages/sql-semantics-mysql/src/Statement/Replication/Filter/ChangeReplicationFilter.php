<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Filter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Rules\Replication\SourceSettings;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CHANGE REPLICATION FILTER filter = (…), … [FOR CHANNEL 'name']`: replaces replication filter rules (MySQL 5.7 and later).
 *
 * Mirrors Sql_cmd_change_repl_filter: the filters in written order. A
 * channel needs MySQL 8.0 or later. Rule: MYSQL-CHANGE-FILTER-001. Facts: a
 * wildcard pattern without a dot (ER_INVALID_RPL_WILD_TABLE_FILTER_PATTERN)
 * and a line feed in a pattern or in the channel name are RefusedSetting
 * diagnostics. The names are not resolved; the statement provides no
 * declaration.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-filter.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Setting two filters
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("change replication filter replicate_do_db = (a, b), replicate_wild_ignore_table = ('a.%')")->toString() // => "CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a, b), REPLICATE_WILD_IGNORE_TABLE = ('a.%')"
 */
final class ChangeReplicationFilter implements Statement
{
    use Snapshot;

    /**
     * @var list<ReplicationFilter> The filters in written order; at least one
     */
    public readonly array $filters;

    /**
     * @param list<ReplicationFilter> $filters The filters in written order; at least one
     * @param Text|null $channel The replication channel, when FOR CHANNEL is written
     */
    public function __construct(array $filters, public readonly ?Text $channel = null)
    {
        $this->filters = Check::listOf($filters, ReplicationFilter::class, 'CHANGE REPLICATION FILTER sets a list of filters.', 1);
    }

    /**
     * Checks the release and reports refused patterns and channel names.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        $releases = new Releases();
        Check::input($release !== GrammarRelease::MySql5651, 'CHANGE REPLICATION FILTER needs MySQL 5.7 or later.');
        Check::input($this->channel === null || !$releases->legacy($release), 'A replication filter channel needs MySQL 8.0 or later.');
        $settings = new SourceSettings();
        foreach ($this->filters as $filter) {
            foreach ($filter->values as $value) {
                if ($value instanceof Text) {
                    $settings->lineFeed($derivation, $value);
                    if (!str_contains($value->value, '.')) {
                        $derivation->report(new RefusedSetting(ReplicationError::WildPattern));
                    }
                }
            }
        }
        $settings->lineFeed($derivation, $this->channel);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHANGE', 'REPLICATION', 'FILTER')->list($this->filters);
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->node($this->channel);
        }
    }
}
