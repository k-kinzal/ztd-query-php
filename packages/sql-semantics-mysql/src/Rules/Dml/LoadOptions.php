<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Platform\MySql\Statement\Dml\DuplicateHandling;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\BulkOptions;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadFormat;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadInput;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadConflict;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\LoadRule;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * Interprets LOAD option compatibility independently of table and file state.
 *
 * MySQL 8.0 and 8.1 ignore bulk annotations. From 8.2, bulk loading excludes
 * XML, column lists, assignments, LOCAL and IGNORE, in that order. A positive
 * COUNT requires bulk loading; zero is the omitted-count interpretation in
 * 8.2 and 8.3 and a syntax refusal from 8.4. Observed through Testcontainers.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LoadOptions
{
    /**
     * Answers the first incompatible combination under the active bulk semantics.
     */
    public function conflict(LoadFormat $format, LoadInput $input, ?DuplicateHandling $duplicates, ?Text $compression, bool $columns, bool $assignments, ?BulkOptions $options): ?LoadConflict
    {
        if (!($options->bulk ?? false)) {
            $rule = $this->input($input, $compression);
        } else {
            $rule = match (true) {
                $format === LoadFormat::Xml => LoadRule::XmlBulk,
                $columns => LoadRule::ColumnsBulk,
                $assignments => LoadRule::AssignmentsBulk,
                $input->local => LoadRule::LocalBulk,
                $duplicates === DuplicateHandling::Ignore => LoadRule::IgnoreBulk,
                default => null,
            };
        }
        return $rule === null ? null : new LoadConflict($rule);
    }

    /**
     * Answers which input option requires bulk loading when it is absent.
     */
    public function input(LoadInput $input, ?Text $compression): ?LoadRule
    {
        return match (true) {
            $input->count !== null && trim($input->count->text, '0') !== '' => LoadRule::MultipleFilesWithoutBulk,
            $input->source === LoadSource::Url => LoadRule::UrlWithoutBulk,
            $compression !== null => LoadRule::CompressionWithoutBulk,
            default => null,
        };
    }
}
