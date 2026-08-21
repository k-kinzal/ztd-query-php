<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * The relationship operators of DBML, whose values are the operators themselves.
 *
 * Many-to-many has no operator here. DBML spells it `<>` and invents a
 * junction table to draw it with, but a fixture has to put rows in that
 * junction table, so it must be named. Writing the two halves separately says
 * the same thing without hiding the table that carries the data.
 */
enum RelationKind: string
{
    /** The left side is the one, the right side is the many. */
    case OneToMany = '<';

    /** The left side is the many, the right side is the one. */
    case ManyToOne = '>';

    case OneToOne = '-';

    /**
     * The end that holds a single row, and is generated first.
     */
    public function parentSide(): RelationSide
    {
        return match ($this) {
            self::OneToMany, self::OneToOne => RelationSide::Left,
            self::ManyToOne => RelationSide::Right,
        };
    }

    /**
     * The end that references the parent, and may hold several rows.
     */
    public function childSide(): RelationSide
    {
        return $this->parentSide()->opposite();
    }

    /**
     * Whether the child end holds a list of rows rather than a single row.
     */
    public function childIsCollection(): bool
    {
        return $this !== self::OneToOne;
    }
}
