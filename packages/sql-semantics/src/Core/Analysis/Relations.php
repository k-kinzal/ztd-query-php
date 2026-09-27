<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Core\Policy\NameRules;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Statement;

/**
 * The tables in force while a statement is resolved.
 *
 * Tables are compared under the dialect's relation name policy, and a
 * name without a schema belongs to the default schema.
 *
 * @visibility SqlSemantics
 */
final class Relations
{
    /**
     * @var list<array{string, string, TableDefinition|null, Statement|null}>
     */
    private array $tables = [];

    /**
     * Starts with no table in force.
     */
    public function __construct(private readonly NameRules $names, private readonly string $defaultSchema)
    {
    }

    /**
     * Applies a reference of a dependency: a declaration puts its table in force, a drop takes it out, and any other reference changes nothing.
     */
    public function apply(Reference $reference, Statement $dependency): void
    {
        [$schema, $name] = $this->qualified($reference->name);
        if ($reference->kind === ReferenceKind::Declaration) {
            $this->declare($schema, $name, $reference->table, $dependency);
        } elseif ($reference->kind === ReferenceKind::Drop) {
            $this->drop($schema, $name);
        }
    }

    /**
     * Puts a table in force, with its readable definition and the statement that declares it, when there are any.
     */
    public function declare(string $schema, string $name, ?TableDefinition $table, ?Statement $owner): void
    {
        $this->tables[] = [$schema, $name, $table, $owner];
    }

    /**
     * Takes a table out of force.
     */
    public function drop(string $schema, string $name): void
    {
        $this->tables = array_values(array_filter($this->tables, fn (array $known): bool => !$this->same($known[0], $known[1], $schema, $name)));
    }

    /**
     * Finds a table in force: its readable definition and the statement that declares it, or null when it is not in force.
     *
     * @return array{TableDefinition|null, Statement|null}|null
     */
    public function lookup(string $schema, string $name): ?array
    {
        foreach ($this->tables as [$knownSchema, $knownName, $table, $owner]) {
            if ($this->same($knownSchema, $knownName, $schema, $name)) {
                return [$table, $owner];
            }
        }

        return null;
    }

    /**
     * Qualifies a name with the default schema, keeping its last two parts.
     *
     * @param non-empty-list<string> $name
     * @return array{string, string}
     */
    public function qualified(array $name): array
    {
        $parts = array_slice($name, -2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : [$this->defaultSchema, $parts[0]];
    }

    /**
     * Compares two qualified table names under the dialect's relation name policy.
     */
    public function same(string $schema, string $name, string $otherSchema, string $otherName): bool
    {
        return $this->names->relationEqual($schema, $otherSchema) && $this->names->relationEqual($name, $otherName);
    }
}
