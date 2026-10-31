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
 * slot in its shape, in the order `*` selects the columns. A position past
 * the slots of the shape selects an implicit slot, counted after them: an
 * INVISIBLE column that a USING list merged.
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
            $slots[] = self::member($visible[$relation], $position);
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
        return self::member($this->visible[$entry[0]], $entry[1]);
    }

    /**
     * Answers the slot of a relation at a star position: a slot of its shape, or past them an implicit slot.
     */
    public static function member(VisibleRelation $relation, int $position): OutputSlot
    {
        $declared = count($relation->shape->slots);

        return $position < $declared ? $relation->shape->slots[$position] : $relation->implicit[$position - $declared]->slot;
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
