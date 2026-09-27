<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\SchemaReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Reference;
use SqlSemantics\Statement\ReferenceKind;
use SqlSemantics\Statement\Resolution;
use SqlSemantics\Statement\Statement;

/**
 * Resolves the table names of a statement against the declarations of its dependencies.
 *
 * The dependencies are applied in order: a declaration adds a table, a drop
 * removes one, and a conditional declaration or drop of a table that is
 * already there or already gone changes nothing. The statement's own names
 * then resolve to a common table expression it defines, to a table a
 * dependency declares, to a table it declares itself, or to a table it
 * drops; any other name is an error, because the dependency that would
 * declare it was not given. A declaration whose columns come from another
 * relation, such as CREATE TABLE ... LIKE or ... AS SELECT, declares its
 * name without a readable table.
 *
 * @visibility SqlSemantics
 */
final class Resolver
{
    private readonly NameSites $sites;

    /**
     * Resolves statements of the language.
     */
    public function __construct(private readonly Language $language, private readonly SchemaReader $reader)
    {
        $vocabulary = $language->vocabulary();
        $platform = $language->dialect->platform();
        $this->sites = new NameSites($vocabulary, $platform->relations(), $platform->names(), new Forms($vocabulary));
    }

    /**
     * Resolves one statement, given its parse tree for the declarations it makes and its dependencies with their resolutions.
     *
     * Declarations are resolved before the other names, so a statement can
     * refer to a table it declares itself.
     *
     * @param list<array{Statement, Resolution}> $dependencies
     *
     * @throws SemanticException When a name resolves to nothing, or a declaration conflicts with one in force
     * @throws LogicException When the relation rules of the dialect do not fit its grammar
     */
    public function resolve(Node $tree, Command $command, array $dependencies): Resolution
    {
        $relations = new Relations($this->language->dialect->platform()->names(), $this->language->dialect->platform()->defaultSchema());
        foreach ($dependencies as [$dependency, $resolution]) {
            foreach ($resolution->references as $reference) {
                $relations->apply($reference, $dependency);
            }
        }
        $declarations = $this->declarations($tree);
        $sites = $this->sites->find($command);
        $references = [];
        foreach ($sites as $index => $site) {
            if ($site->kind === ReferenceKind::Declaration) {
                $references[$index] = $this->declare($site, $relations, $declarations, $tree);
            }
        }
        foreach ($sites as $index => $site) {
            if ($site->kind !== ReferenceKind::Declaration) {
                $references[$index] = $this->refer($site, $relations, $tree);
            }
        }
        ksort($references);
        $declared = [];
        foreach ($references as $reference) {
            if ($reference->kind === ReferenceKind::Declaration && $reference->table !== null && !in_array($reference->table, $declared, true)) {
                $declared[] = $reference->table;
            }
        }

        return new Resolution(array_map(static fn (array $pair): Statement => $pair[0], $dependencies), $declared, array_values($references));
    }

    /**
     * Resolves a declaration site: a new table is put in force, and a conditional declaration of a table in force refers to it instead.
     *
     * @param list<TableDefinition> $declarations The readable declarations of the statement
     *
     * @throws SemanticException When the table is in force and the declaration is not conditional
     */
    public function declare(NameSite $site, Relations $relations, array $declarations, Node $tree): Reference
    {
        [$schema, $table] = $relations->qualified($site->name);
        $known = $relations->lookup($schema, $table);
        if ($known !== null && !$site->conditional) {
            throw new SemanticException('duplicate-table', 'Duplicate table declaration: ' . $table, $tree);
        }
        if ($known !== null) {
            return new Reference($site->value, $site->name, ReferenceKind::Dependency, $known[1], $known[0], true);
        }
        $definition = $this->declared($declarations, $relations, $schema, $table);
        $relations->declare($schema, $table, $definition, null);

        return new Reference($site->value, $site->name, ReferenceKind::Declaration, null, $definition, $site->conditional);
    }

    /**
     * Resolves a site that defines, drops or refers to a table: to a common table expression, to a table in force, or to nothing.
     *
     * @throws SemanticException When the name is not in force, and the site is not a conditional drop
     */
    public function refer(NameSite $site, Relations $relations, Node $tree): Reference
    {
        if ($site->kind === ReferenceKind::CommonTableExpression) {
            $relations->define($site->name);

            return new Reference($site->value, $site->name, ReferenceKind::CommonTableExpression);
        }
        [$schema, $table] = $relations->qualified($site->name);
        $known = $relations->lookup($schema, $table);
        if ($site->kind === ReferenceKind::Drop) {
            if ($known === null && !$site->conditional) {
                throw new SemanticException('unknown-table', 'Cannot drop an unknown table: ' . $table, $tree);
            }

            return new Reference($site->value, $site->name, ReferenceKind::Drop, $known[1] ?? null, $known[0] ?? null, $site->conditional);
        }
        if ($relations->isCommon($site->name)) {
            return new Reference($site->value, $site->name, ReferenceKind::CommonTableExpression);
        }
        if ($known === null) {
            throw new SemanticException('unknown-table', 'No dependency declares the table ' . implode('.', $site->name), $tree);
        }

        return new Reference($site->value, $site->name, $known[1] === null ? ReferenceKind::Declaration : ReferenceKind::Dependency, $known[1], $known[0]);
    }

    /**
     * Reads the tables the statement declares with their columns, from its parse tree.
     *
     * A declaration whose columns come from another relation, such as
     * CREATE TABLE ... LIKE or ... AS SELECT, is not readable and is left out;
     * its name still resolves as a declaration without a table.
     *
     * @return list<TableDefinition>
     *
     * @throws SemanticException When a declaration is readable but invalid
     */
    public function declarations(Node $tree): array
    {
        $platform = $this->language->dialect->platform();
        $tables = [];
        foreach (Tree::outer($tree, [$platform->statementNames()[1], ...$platform->syntax()->nodes('statement')]) as $statement) {
            $create = Tree::child($statement, $platform->syntax()->nodes('createTable'));
            if ($create === null) {
                continue;
            }
            try {
                $tables[] = $this->reader->table($platform->schema()->schemaNode($statement, $create));
            } catch (SemanticException $error) {
                if ($error->reason !== 'unsupported-syntax') {
                    throw $error;
                }
            }
        }

        return $tables;
    }

    /**
     * Finds the readable declaration of a table among those of the statement.
     *
     * @param list<TableDefinition> $declarations
     */
    public function declared(array $declarations, Relations $relations, string $schema, string $name): ?TableDefinition
    {
        foreach ($declarations as $table) {
            if ($relations->same($table->schema, $table->name, $schema, $name)) {
                return $table;
            }
        }

        return null;
    }
}
