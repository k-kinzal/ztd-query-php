<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem\UtilityProblemKind;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the row EXPLAIN returns.
 *
 * Rule: PG-EXPLAIN-ROWS-001. EXPLAIN returns one column named `QUERY PLAN`
 * that is never NULL. The server reads the FORMAT options in order, so the
 * last one decides: the value `xml` gives the type `xml`, `json` gives
 * `json`, `text` and `yaml` give `text`, and without the option the format
 * is text. The value is compared exactly as received, an unquoted word
 * already folded to lower case. Diagnostics: a FORMAT option without a
 * value, and a value that is none of the four; the column is then typed as
 * text. Precision: `Known`. Termination: one pass over the options.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ExplainFacts
{
    /**
     * The type of the plan column for each format.
     */
    private const TYPES = ['text' => Builtin::Text, 'yaml' => Builtin::Text, 'xml' => Builtin::Xml, 'json' => Builtin::Json];

    /**
     * Answers the plan row and reports each FORMAT option the server rejects.
     *
     * @param list<UtilityOption> $options
     */
    public function rows(Derivation $derivation, array $options): QueryFact
    {
        $type = Builtin::Text;
        foreach ($options as $option) {
            if ($option->option() !== 'format') {
                continue;
            }
            $format = $option->text();
            if ($format === null) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::MissingArgument, ['format']));
            } elseif (!isset(self::TYPES[$format])) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::UnknownFormat, ['format', $format]));
            } else {
                $type = self::TYPES[$format];
            }
        }

        return new QueryFact([new Field(0, new OutputSlot(new Name('QUERY PLAN'), new Known($type), Nullability::NotNull))], $derivation->context->columnNames);
    }
}
