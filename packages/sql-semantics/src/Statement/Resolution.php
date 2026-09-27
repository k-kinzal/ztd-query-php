<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Statement\Declaration\TableDefinition;

/**
 * What a statement means against the statements it depends on.
 *
 * A statement is analyzed with its dependencies: the declarations that came
 * before it, in order. The resolution keeps those dependencies, the tables
 * the statement itself declares, and every table name it writes with what
 * that name resolves to. Partial analysis records missing references with
 * ReferenceKind::Undeclared; strict analysis rejects them.
 *
 * @visibility public
 * @example Reading what a declaration declares
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $semantics->analyze('CREATE TABLE users (id INTEGER PRIMARY KEY)', [])->resolution?->declarations[0]->columns[0]->name // => 'id'
 */
final class Resolution
{
    /**
     * @param list<Statement> $dependencies The statements this one was analyzed against, in order
     * @param list<TableDefinition> $declarations The tables this statement declares
     * @param list<Reference> $references Every table name written in the statement, in writing order
     */
    public function __construct(
        public readonly array $dependencies = [],
        public readonly array $declarations = [],
        public readonly array $references = [],
    ) {
        Declaration\Invariant::members($dependencies, Statement::class);
        Declaration\Invariant::members($declarations, TableDefinition::class);
        Declaration\Invariant::members($references, Reference::class);
    }

    /**
     * Lists the references that name a table declared by a dependency.
     *
     * @return list<Reference>
     */
    public function tables(): array
    {
        return array_values(array_filter($this->references, static fn (Reference $reference): bool => $reference->kind === ReferenceKind::Dependency));
    }
}
