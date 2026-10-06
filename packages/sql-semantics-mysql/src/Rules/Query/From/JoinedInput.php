<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\From;

use SqlSemantics\Resolution\LookupLevel;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * The outcome of deriving a FROM clause term: its facts, the relations it makes visible and the columns `*` selects.
 *
 * Each star entry is the index of a visible relation and the position of a
 * slot in its shape, in the order `*` selects the columns.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class JoinedInput
{
    /**
     * @param RelationFact $fact The facts of the term
     * @param list<VisibleRelation> $visible The relations a name can refer to, in written order
     * @param list<array{int, int}> $star The columns `*` selects
     */
    public function __construct(public readonly RelationFact $fact, public readonly array $visible, public readonly array $star)
    {
    }

    /**
     * Answers the term with the shape `*` selects as its facts.
     *
     * @param list<VisibleRelation> $visible The relations a name can refer to
     * @param list<array{int, int}> $star The columns `*` selects
     */
    public static function of(array $visible, array $star): self
    {
        $slots = [];
        foreach ($star as [$relation, $position]) {
            $slots[] = $visible[$relation]->shape->slots[$position];
        }
        $missing = [];
        foreach ($visible as $relation) {
            array_push($missing, ...$relation->shape->missing);
        }

        return new self(new RelationFact(new RowShape($slots, $missing)), $visible, $star);
    }

    /**
     * Answers the slot of a star entry.
     *
     * @param array{int, int} $entry The star entry
     */
    public function slot(array $entry): OutputSlot
    {
        return $this->visible[$entry[0]]->shape->slots[$entry[1]];
    }

    /**
     * Tells whether the names of every visible relation are decided: its shape is complete and no column name depends on missing inputs.
     */
    public function complete(): bool
    {
        foreach ($this->visible as $relation) {
            if (LookupLevel::undecided($relation) !== []) {
                return false;
            }
        }

        return true;
    }
}
