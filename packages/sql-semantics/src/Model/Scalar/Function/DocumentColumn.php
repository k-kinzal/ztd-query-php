<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\TableFunction;

/**
 * A value produced by one declared column of a JSON or XML row expansion.
 * @visibility public
 * @example Reading a declared JSON_TABLE output
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
 *     $column = $query->from->outputs[0]->expression;
 *     $column->spelling() // => 'n'
 *     $column->nullability->value // => 'not-null'
 */
final class DocumentColumn extends Expression
{
    /**
     * Retains the table operation and its precise column declaration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|\SqlParser\Lexer\Token $source, public readonly TableFunction\Json\JsonTable|TableFunction\Xml\XmlTable $table, public readonly TableFunction\Json\Ordinality|TableFunction\Json\ValueColumn|TableFunction\Json\ExistsColumn|TableFunction\Xml\Ordinality|TableFunction\Xml\ValueColumn $column)
    {
        $columns = $table instanceof TableFunction\Json\JsonTable ? array_column(TableFunction\OutputColumns::json($table->columns), 0) : $table->columns;
        if (!in_array($column, $columns, true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The document column must belong to its table operation.');
        }
        if (serialize($facts->type) !== serialize(TableFunction\OutputColumns::type($column, $table->dialect))) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Document output facts must retain the declared column type.');
        }
        parent::__construct($facts, $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::DocumentColumn;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return TableFunction\Dependencies::of($this->table);
    }

    /**
     * Returns the declaration's output label, never source SQL.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->column->name;
    }

    /**
     * Retains the declaration when NULL extension changes the output facts.
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->table, $this->column);
    }
}
