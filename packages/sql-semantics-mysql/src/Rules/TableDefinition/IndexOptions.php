<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;

/**
 * Constrains index options to the grammar of their index kind.
 *
 * WITH PARSER belongs to FULLTEXT; USING belongs to ordinary and unique keys.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IndexOptions
{
    /**
     * @param list<IndexOption> $options The options in written order
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When an option belongs to a different index kind
     */
    public function check(IndexKind $kind, array $options): void
    {
        foreach ($options as $option) {
            Check::input(!$option instanceof IndexParser || $kind === IndexKind::FullText, 'WITH PARSER is an option of a FULLTEXT index.');
            Check::input(!$option instanceof IndexUsing || !in_array($kind, [IndexKind::FullText, IndexKind::Spatial], true), 'A FULLTEXT or SPATIAL index has no USING option.');
        }
    }
}
