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
 * Derives the row EXPLAIN returns and checks the options that depend on each other.
 *
 * Rule: PG-EXPLAIN-ROWS-001. EXPLAIN returns one column named `QUERY PLAN`
 * that is never NULL. The server reads the FORMAT options in order, so the
 * last one decides: the value `xml` gives the type `xml`, `json` gives
 * `json`, `text` and `yaml` give `text`, and without the option the format
 * is text. The value is compared exactly as received, an unquoted word
 * already folded to lower case. Diagnostic: a value that is none of the
 * four; the column is then typed as text. Precision: `Known`.
 * Rule: PG-EXPLAIN-OPTIONS-001. SERIALIZE (release 17) without a value
 * means `text`, and its value is one of `off`, `none`, `text` and `binary`,
 * compared exactly. The options WAL, TIMING and SERIALIZE other than
 * `off`/`none` require ANALYZE; TIMING is checked only when it is written,
 * because it otherwise follows ANALYZE. ANALYZE and GENERIC_PLAN exclude
 * each other. Each Boolean option is read by PG-UTILITY-OPTION-VALUE-001.
 * Termination: one pass over the options.
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
     * The values SERIALIZE takes.
     */
    private const SERIALIZE = ['off', 'none', 'text', 'binary'];

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
                continue;
            }
            if (!isset(self::TYPES[$format])) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::UnknownFormat, ['format', $format]));
            } else {
                $type = self::TYPES[$format];
            }
        }

        return new QueryFact([new Field(0, new OutputSlot(new Name('QUERY PLAN'), new Known($type), Nullability::NotNull))], $derivation->context->columnNames);
    }

    /**
     * Reports each SERIALIZE value the server rejects and each combination of options it refuses.
     *
     * @param list<UtilityOption> $options
     */
    public function checks(Derivation $derivation, array $options): void
    {
        $values = new OptionArguments();
        $analyze = $values->enabled($options, 'analyze', false);
        $serializable = in_array('serialize', (new OptionRules())->known('EXPLAIN', $derivation->context->profile->grammar), true);
        $serialize = false;
        foreach ($options as $option) {
            $value = $serializable && $option->option() === 'serialize' ? ($option->text() ?? 'text') : null;
            if ($value !== null && !in_array($value, self::SERIALIZE, true)) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::UnknownFormat, ['serialize', $value]));
            } elseif ($value !== null) {
                $serialize = $value === 'text' || $value === 'binary';
            }
        }
        $timing = (new OptionRules())->find($options, 'timing') !== null && $values->enabled($options, 'timing', false);
        foreach (['WAL' => $values->enabled($options, 'wal', false), 'TIMING' => $timing, 'SERIALIZE' => $serialize] as $name => $enabled) {
            if ($enabled && !$analyze) {
                $derivation->report(new UtilityProblem(UtilityProblemKind::RequiresAnalyze, [$name]));
            }
        }
        if ($analyze && $values->enabled($options, 'generic_plan', false)) {
            $derivation->report(new UtilityProblem(UtilityProblemKind::GenericPlanWithAnalyze));
        }
    }
}
