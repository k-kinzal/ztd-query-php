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
 * Tables are compared under the dialect's relation name policy. A name
 * without a schema refers to the table of the first schema of the search
 * path that has one, and is declared in the first schema of the path. A
 * table that was dropped is remembered as gone until it is declared again.
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
     * @var list<array{string, string}>
     */
    private array $dropped = [];

    /**
     * Starts with no table in force.
     *
     * @param non-empty-list<string> $path The schemas an unqualified name is read in, in order
     */
    public function __construct(private readonly NameRules $names, private readonly array $path)
    {
    }

    /**
     * Applies a reference of a dependency: a declaration puts its table in force, a drop takes it out, and any other reference changes nothing.
     */
    public function apply(Reference $reference, Statement $dependency): void
    {
        if ($reference->kind === ReferenceKind::Declaration) {
            [$schema, $name] = $this->qualified($reference->name);
            $this->declare($schema, $name, $reference->table, $dependency);
        } elseif ($reference->kind === ReferenceKind::Drop) {
            $found = $this->find($reference->name) ?? $this->qualified($reference->name);
            $this->drop($found[0], $found[1]);
        }
    }

    /**
     * Puts a table in force, with its readable definition and the statement that declares it, when there are any.
     */
    public function declare(string $schema, string $name, ?TableDefinition $table, ?Statement $owner): void
    {
        $this->tables[] = [$schema, $name, $table, $owner];
        $this->dropped = array_values(array_filter($this->dropped, fn (array $gone): bool => !$this->same($gone[0], $gone[1], $schema, $name)));
    }

    /**
     * Takes a table out of force.
     */
    public function drop(string $schema, string $name): void
    {
        $this->tables = array_values(array_filter($this->tables, fn (array $known): bool => !$this->same($known[0], $known[1], $schema, $name)));
        $this->dropped[] = [$schema, $name];
    }

    /**
     * Reports whether a name refers to no table because the tables it could refer to were dropped: in its schema, or in every schema of the search path.
     *
     * @param non-empty-list<string> $name
     */
    public function gone(array $name): bool
    {
        $parts = array_slice($name, -2);
        $table = $parts[count($parts) - 1];
        foreach (count($parts) === 2 ? [$parts[0]] : $this->path as $schema) {
            if (array_filter($this->dropped, fn (array $gone): bool => $this->same($gone[0], $gone[1], $schema, $table)) === []) {
                return false;
            }
        }

        return true;
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
     * Finds the table in force a name refers to: a qualified name in its schema, an unqualified one in the first schema of the search path that has it.
     *
     * @param non-empty-list<string> $name
     * @return array{string, string, TableDefinition|null, Statement|null}|null The schema and name the table is in force under, its definition and its owner
     */
    public function find(array $name): ?array
    {
        $parts = array_slice($name, -2);
        $table = $parts[count($parts) - 1];
        foreach (count($parts) === 2 ? [$parts[0]] : $this->path as $schema) {
            foreach ($this->tables as [$knownSchema, $knownName, $definition, $owner]) {
                if ($this->same($knownSchema, $knownName, $schema, $table)) {
                    return [$knownSchema, $knownName, $definition, $owner];
                }
            }
        }

        return null;
    }

    /**
     * Qualifies a name with the schema an unqualified declaration creates its table in, keeping its last two parts.
     *
     * @param non-empty-list<string> $name
     * @return array{string, string}
     */
    public function qualified(array $name): array
    {
        $parts = array_slice($name, -2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : [$this->path[0], $parts[0]];
    }

    /**
     * Compares two qualified table names under the dialect's relation name policy.
     */
    public function same(string $schema, string $name, string $otherSchema, string $otherName): bool
    {
        return $this->names->relationEqual($schema, $otherSchema) && $this->names->relationEqual($name, $otherName);
    }
}
