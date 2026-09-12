<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

/**
 * One relation as written, with the roles the operator implies.
 *
 * The sides are kept in the order they were written so that a plan prints back
 * the way it was given. Which end is the parent comes from the operator, not
 * from the order, so `a.id < b.a_id` and `b.a_id > a.id` describe the same
 * shape.
 *
 * A `?` next to an end marks that end optional, as it does in DBML: the
 * referencing column may be null, so the row on the other end need not exist.
 */
final class Relation
{
    /**
     * Initializes the collaborators and declared state for this object.
     * @throws PlanSyntaxException
     */
    public function __construct(
        public readonly ColumnRef $left,
        public readonly RelationKind $kind,
        public readonly ColumnRef $right,
        public readonly bool $leftOptional = false,
        public readonly bool $rightOptional = false,
    ) {
        if (count($left->columns) !== count($right->columns)) {
            throw PlanSyntaxException::compositeArityMismatch($left, $right);
        }
    }

    /**
     * `parent.column < child.column`: one parent row, several child rows.
     */
    public static function oneToMany(
        string|ColumnRef $parent,
        string|ColumnRef $child,
        bool $childOptional = false,
    ): self {
        return new self(
            ($parent instanceof ColumnRef ? $parent : ColumnRef::from($parent)),
            RelationKind::OneToMany,
            ($child instanceof ColumnRef ? $child : ColumnRef::from($child)),
            false,
            $childOptional
        );
    }

    /**
     * `child.column > parent.column`: the same shape written from the child.
     */
    public static function manyToOne(
        string|ColumnRef $child,
        string|ColumnRef $parent,
        bool $parentOptional = false,
    ): self {
        return new self(
            ($child instanceof ColumnRef ? $child : ColumnRef::from($child)),
            RelationKind::ManyToOne,
            ($parent instanceof ColumnRef ? $parent : ColumnRef::from($parent)),
            false,
            $parentOptional
        );
    }

    /**
     * `parent.column - child.column`: one row on each side.
     */
    public static function oneToOne(
        string|ColumnRef $parent,
        string|ColumnRef $child,
        bool $childOptional = false,
    ): self {
        return new self(
            ($parent instanceof ColumnRef ? $parent : ColumnRef::from($parent)),
            RelationKind::OneToOne,
            ($child instanceof ColumnRef ? $child : ColumnRef::from($child)),
            false,
            $childOptional
        );
    }

    /**
     * The fewest child rows to generate when none are given.
     *
     * A relation written without `?` says the child must be there, so an
     * unspecified child is still generated. Marking it optional is what makes
     * "none at all" a possible outcome.
     */
    public function minimumChildRows(): int
    {
        return $this->childIsOptional() ? 0 : 1;
    }

    /**
     * The most child rows the relation allows, or null where it is unbounded.
     */
    public function maximumChildRows(): ?int
    {
        return $this->childIsCollection() ? null : 1;
    }

    /**
     * The end holding a single row, generated before the other.
     */
    public function parent(): ColumnRef
    {
        return $this->kind->parentSide() === RelationSide::Left ? $this->left : $this->right;
    }

    /**
     * The end referencing the parent, which may hold several rows.
     */
    public function child(): ColumnRef
    {
        return $this->kind->childSide() === RelationSide::Left ? $this->left : $this->right;
    }

    /**
     * Whether the parent row may be absent, leaving the reference null.
     */
    public function parentIsOptional(): bool
    {
        return $this->kind->parentSide() === RelationSide::Left ? $this->leftOptional : $this->rightOptional;
    }

    /**
     * Whether the child end was marked optional.
     *
     * This says nothing the data does not already say, since the number of
     * child rows comes from the values supplied for that table.
     */
    public function childIsOptional(): bool
    {
        return $this->kind->childSide() === RelationSide::Left ? $this->leftOptional : $this->rightOptional;
    }

    /**
     * Reports whether the relation permits multiple child rows.
     */
    public function childIsCollection(): bool
    {
        return $this->kind->childIsCollection();
    }

    /**
     * Map each child column onto the parent column it references.
     *
     * @return array<string, string> Child column => parent column
     */
    public function columnMap(): array
    {
        $childColumns = $this->child()->columns;
        $parentColumns = $this->parent()->columns;

        $map = [];
        foreach ($childColumns as $index => $column) {
            $map[$column] = $parentColumns[$index];
        }

        return $map;
    }

    /**
     * @return list<string> The tables this relation touches, left end first
     */
    public function tables(): array
    {
        if ($this->left->table === $this->right->table) {
            return [$this->left->table];
        }

        return [$this->left->table, $this->right->table];
    }






}
