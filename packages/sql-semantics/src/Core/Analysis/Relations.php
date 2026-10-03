<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Core\Policy\NameRules;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Statement;

/**
 * The declarations visible while a statement is resolved.
 *
 * Context declarations do not depend on input order. Statements describing
 * writes, ALTER, or DROP never change them. The search path determines
 * which schema an unqualified relation name denotes.
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
     * Starts with no declarations.
     *
     * @param non-empty-list<string> $path The schemas an unqualified name is read in, in order
     * @param list<string> $implicitSchemas Implicit namespaces searched before the session path for declared tables
     */
    public function __construct(private readonly NameRules $names, private readonly array $path, private readonly array $implicitSchemas = [])
    {
    }

    /**
     * Imports declarations from a dependency without applying its operations.
     *
     * @throws SemanticException When distinct dependencies declare the same qualified table
     */
    public function apply(Reference $reference, Statement $dependency): void
    {
        if ($reference->kind !== ReferenceKind::Declaration) {
            return;
        }
        [$schema, $name] = ($reference->table === null || $reference->table->schema === '') ? $this->qualified($reference->name) : [$reference->table->schema, $reference->table->name];
        $known = $this->lookup($schema, $name);
        if ($known !== null) {
            if ($known[1] !== $dependency) {
                throw new SemanticException('duplicate-table', 'Conflicting context declarations: ' . $name, $reference->value);
            }

            return;
        }
        $this->declare($schema, $name, $reference->table, $dependency);
    }

    /**
     * Adds a declaration to the innermost scope, retaining its exact definition and owner.
     */
    public function declare(string $schema, string $name, ?TableDefinition $table, ?Statement $owner): void
    {
        array_unshift($this->tables, [$schema, $name, $table, $owner]);
    }

    /**
     * Finds a declared table and its owner, or null when the context does not declare it.
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
     * Finds the declaration a name refers to: a qualified name in its schema, an unqualified one in the first schema of the search path that has it.
     *
     * @param non-empty-list<string> $name
     * @return array{string, string, TableDefinition|null, Statement|null}|null The qualified name, definition and owner
     */
    public function find(array $name): ?array
    {
        $parts = array_slice($name, -2);
        $table = $parts[count($parts) - 1];
        foreach (count($parts) === 2 ? [$parts[0]] : [...$this->implicitSchemas, ...$this->path] as $schema) {
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
