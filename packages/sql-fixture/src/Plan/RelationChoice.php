<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use SqlFixture\Plan\Choice\ChoiceCase;
use SqlFixture\Plan\Choice\ChoiceDefinitionException;

/**
 * Selects a set of relations for each row using one discriminator column.
 * @visibility public
 * @example Define alternative parents
 *     $choice = \SqlFixture\Plan\RelationChoice::on('comments.kind');
 *     $choice = $choice->when('post', \SqlFixture\Plan\Relation::manyToOne('comments.target_id', 'posts.id'));
 *     $choice = $choice->when('none');
 *     count($choice->cases) // => 2
 */
final class RelationChoice
{
    /**
     * @param list<ChoiceCase> $cases
     * @throws ChoiceDefinitionException
     */
    public function __construct(
        public readonly ColumnRef $discriminator,
        public readonly array $cases = [],
        public readonly ?ChoiceCase $fallback = null,
    ) {
        if ($discriminator->isComposite()) {
            throw new ChoiceDefinitionException($discriminator, 'Use exactly one discriminator column.');
        }
    }

    /**
     * Starts an immutable choice for a table column.
     */
    public static function on(string|ColumnRef $discriminator): self
    {
        return new self($discriminator instanceof ColumnRef ? $discriminator : ColumnRef::from($discriminator));
    }

    /**
     * Adds a case; an empty relation list explicitly means no relationship.
     * @throws ChoiceDefinitionException
     */
    public function when(string|int|bool|null $value, Relation ...$relations): self
    {
        foreach ($this->cases as $case) {
            if ($case->value === $value) {
                throw new ChoiceDefinitionException($this->discriminator, 'Each discriminator value must be unique.');
            }
        }

        return new self($this->discriminator, [...$this->cases, new ChoiceCase($value, array_values($relations))], $this->fallback);
    }

    /**
     * Handles explicitly supplied values not named by a case.
     * @throws ChoiceDefinitionException
     */
    public function otherwise(Relation ...$relations): self
    {
        if ($this->fallback !== null) {
            throw new ChoiceDefinitionException($this->discriminator, 'A fallback is already defined.');
        }

        return new self($this->discriminator, $this->cases, new ChoiceCase(null, array_values($relations)));
    }

    /**
     * Includes the fallback when inspecting every possible branch.
     * @return list<ChoiceCase>
     */
    public function branches(): array
    {
        return $this->fallback === null ? $this->cases : [...$this->cases, $this->fallback];
    }

    /**
     * Lists every potential relation without selecting a branch.
     * @return list<Relation>
     */
    public function relations(): array
    {
        $relations = [];
        foreach ($this->branches() as $case) {
            $relations = [...$relations, ...$case->relations];
        }

        return $relations;
    }
}
