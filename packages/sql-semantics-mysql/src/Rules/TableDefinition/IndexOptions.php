<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Constrains index options to their index kind and resolves full-text parser dependencies.
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

    /**
     * Records an aborting notice for each missing plugin in written order.
     *
     * Unknown plugins stop interpretation before later table options or partition
     * expressions. The supplied catalog permits direct construction without SQL or ASTs.
     *
     * @param list<IndexOption> $options The options of a full-text index
     */
    public function parsers(array $options, Derivation $derivation): void
    {
        foreach ($options as $option) {
            if (!$option instanceof IndexParser) {
                continue;
            }
            $problem = $option->problem(Settings::of($derivation->context));
            if ($problem !== null) {
                $derivation->report($problem);
                $derivation->warn(new ParseFailure($problem, true), $option);
            }
        }
    }
}
