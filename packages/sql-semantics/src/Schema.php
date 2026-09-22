<?php

declare(strict_types=1);

namespace SqlSemantics;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\TableDefinition;

/**
 * A closed schema snapshot. Missing declarations are errors, not invented tables.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $schema->dialect->value // => 'postgresql'
 *
 * @visibility public
 */
final class Schema
{
    /**
     * @var list<Schema\FunctionSignature>
     */
    public readonly array $functions;

    /**
     * @param Dialect $dialect Language used to interpret the declarations
     * @param list<TableDefinition> $tables Declarations visible to semantic binding
     * @param string $defaultSchema Schema used for unqualified table declarations and references
     * @param string $grammarVersion Resolved sql-parser grammar release shared by DDL and SELECT
     * @param list<Node> $statements Ordered original schema statements, including auxiliary objects
     * @param list<Schema\FunctionSignature>|null $functions Function overloads; null registers the defaults
     * @param list<Schema\VariableDefinition> $variables Declared variables without runtime values
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly array $tables,
        public readonly string $defaultSchema,
        public readonly string $grammarVersion,
        public readonly array $statements = [],
        ?array $functions = null,
        public readonly array $variables = [],
    ) {
        Model\Validation\Collections::objects($tables, TableDefinition::class);
        Model\Validation\Collections::objects($statements, Node::class);
        foreach ($tables as $table) {
            if ($table->properties !== null && $table->properties->dialect() !== $dialect) {
                throw new InvalidStructure('A table must use the schema dialect.');
            }
            foreach ($table->columns as $column) {
                if ($column->type->dialect !== $dialect) {
                    throw new InvalidStructure('A column must use the schema dialect.');
                }
            }
        }
        Model\Validation\Collections::objects($variables, Schema\VariableDefinition::class);
        foreach ($variables as $variable) {
            if ($variable->type->dialect !== $dialect) {
                throw new InvalidStructure('A variable must use the schema dialect.');
            }
        }
        $this->functions = $functions ?? Schema\Functions\Builtins::forDialect($dialect);
        Model\Validation\Collections::objects($this->functions, Schema\FunctionSignature::class);
        foreach ($this->functions as $function) {
            Schema\Functions\Registration::check($dialect, $function);
        }
    }

    /**
     * Returns an independent snapshot with added or replaced function overloads.
     * An overload has the same identity when its name, namespace, and parameters agree.
     *
     * @throws InvalidStructure
     */
    public function withFunctions(Schema\FunctionSignature ...$functions): self
    {
        $registered = $this->functions;
        foreach ($functions as $function) {
            Schema\Functions\Registration::check($this->dialect, $function);
            $registered = array_values(array_filter($registered, fn (Schema\FunctionSignature $existing): bool => ($this->dialect === Dialect::PostgreSql ? $existing->name !== $function->name : strcasecmp($existing->name, $function->name) !== 0) || $existing->schema !== $function->schema || serialize($existing->parameters) !== serialize($function->parameters)));
            $registered[] = $function;
        }
        return new self($this->dialect, $this->tables, $this->defaultSchema, $this->grammarVersion, $this->statements, $registered, $this->variables);
    }

    /**

     * Adds or replaces declarations by namespace and name without assigning values.

     */
    public function withVariables(Schema\VariableDefinition ...$variables): self
    {
        $registered = $this->variables;
        foreach ($variables as $variable) {
            $registered = array_values(array_filter($registered, static fn (Schema\VariableDefinition $existing): bool => $existing->scope !== $variable->scope || strcasecmp($existing->name, $variable->name) !== 0));
            $registered[] = $variable;
        }
        return new self($this->dialect, $this->tables, $this->defaultSchema, $this->grammarVersion, $this->statements, $this->functions, $registered);
    }
}
