<?php

declare(strict_types=1);

namespace SqlSemantics;

use InvalidArgumentException;
use SqlParser\Parser\Node;
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
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly array $tables,
        public readonly string $defaultSchema,
        public readonly string $grammarVersion,
        public readonly array $statements = [],
        ?array $functions = null,
    ) {
        $this->functions = $functions ?? Schema\Functions\Builtins::forDialect($dialect);
    }

    /**
     * Returns an independent snapshot with added or replaced function overloads.
     * An overload has the same identity when its name, namespace, and parameters agree.
     *
     * @throws InvalidArgumentException
     */
    public function withFunctions(Schema\FunctionSignature ...$functions): self
    {
        $registered = $this->functions;
        foreach ($functions as $function) {
            foreach ($function->parameters ?? [] as $parameter) {
                if ($parameter->dialect !== $this->dialect) {
                    throw new InvalidArgumentException('A function must use the schema dialect.');
                }
            }
            if ($function->returnType instanceof Type\TypeDescriptor && $function->returnType->dialect !== $this->dialect) {
                throw new InvalidArgumentException('A function must use the schema dialect.');
            }
            $registered = array_values(array_filter($registered, fn (Schema\FunctionSignature $existing): bool => ($this->dialect === Dialect::PostgreSql ? $existing->name !== $function->name : strcasecmp($existing->name, $function->name) !== 0) || $existing->schema !== $function->schema || serialize($existing->parameters) !== serialize($function->parameters)));
            $registered[] = $function;
        }
        return new self($this->dialect, $this->tables, $this->defaultSchema, $this->grammarVersion, $this->statements, $registered);
    }
}
