<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Choice;

use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationChoice;
use SqlFixture\Plan\Validation\TableName;

/**
 * Collects unconditional and alternative relations in declaration order.
 * @visibility root
 */
final class PlanContents
{
    /**
     * @var list<Relation>
     */
    public array $unconditional = [];
    /**
     * @var list<Relation>
     */
    public array $relations = [];
    /**
     * @var list<RelationChoice>
     */
    public array $choices = [];
    /**
     * @var list<string>
     */
    public array $tables = [];

    /**
     * @param list<Relation|RelationChoice|string> $parts
     */
    public function __construct(array $parts)
    {
        foreach ($parts as $part) {
            if (is_string($part)) {
                $this->tables[] = (new TableName())->assertTableName($part);
                continue;
            }
            if ($part instanceof RelationChoice) {
                $this->choices[] = $part;
                $this->tables[] = $part->discriminator->table;
                foreach ($part->relations() as $relation) {
                    $this->append($relation);
                }
                continue;
            }
            $this->unconditional[] = $part;
            $this->append($part);
        }
        $this->tables = array_values(array_unique($this->tables));
    }

    /**
     * Records a relation and both endpoint tables.
     */
    public function append(Relation $relation): void
    {
        $this->relations[] = $relation;
        $this->tables = [...$this->tables, ...$relation->tables()];
    }
}
