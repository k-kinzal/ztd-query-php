<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Makes the row of the trigger table visible as NEW and OLD in a trigger body.
 *
 * Rule: MYSQL-TRIGGER-ROWS-001. `NEW.col` denotes a column of the row to
 * be inserted or of the row after an update, `OLD.col` a column of the row
 * before an update or a delete: an INSERT trigger has no OLD row and a
 * DELETE trigger no NEW row. The two words are compared without regard to
 * letter case, so each is visible under every spelling the name comparison
 * of the context tells apart. A column is reached through the qualifier
 * only; no unqualified name denotes a column of the row. Terminates: at
 * most sixteen spellings. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/trigger-syntax.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RowAliases
{
    /**
     * Answers the occurrences NEW and OLD of the trigger table for a triggering event.
     *
     * @return list<VisibleRelation>
     */
    public function visible(TriggerTable $table, RelationFact $fact, TriggerEvent $event, AnalysisContext $context): array
    {
        $words = match ($event) {
            TriggerEvent::Insert => ['NEW'],
            TriggerEvent::Update => ['NEW', 'OLD'],
            TriggerEvent::Delete => ['OLD'],
        };
        $relations = [];
        $implicit = (new TableShapes())->implicit($fact);
        $hidden = array_keys($fact->shape->slots);
        foreach ($words as $word) {
            foreach ($this->spellings($word, $context) as $spelling) {
                $relations[] = new VisibleRelation($table, $fact->shape, new Name($spelling), null, $hidden, $implicit);
            }
        }

        return $relations;
    }

    /**
     * Answers the letter-case spellings of a word that the context compares as different names.
     *
     * @return list<string>
     */
    public function spellings(string $word, AnalysisContext $context): array
    {
        $spellings = [];
        for ($mask = 0; $mask < 2 ** strlen($word); $mask++) {
            $spelling = '';
            for ($index = 0; $index < strlen($word); $index++) {
                $spelling .= (($mask >> $index) & 1) === 1 ? strtolower($word[$index]) : $word[$index];
            }
            foreach ($spellings as $known) {
                if ($context->relationNames->equal($known, $spelling)) {
                    continue 2;
                }
            }
            $spellings[] = $spelling;
        }

        return $spellings;
    }
}
