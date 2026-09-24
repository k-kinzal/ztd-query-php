<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Retrieval;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL `SELECT ... INTO @a, @b`: the query's single row is stored in user variables, one per selected column, instead of being returned.
 *
 * @visibility public
 * @example Reading the receiving user variables
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a, b INTO @first, @second FROM t');
 *     [$statement->variables, count($statement->query->resultColumns())] // => [['first', 'second'], 2]
 */
final class SelectIntoVariablesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<string> $variables User variable names without `@`, in column order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly BoundQuery $query, public readonly array $variables)
    {
        RetrievedQuery::check($origin, $query, Dialect::MySql);
        Collections::nonEmpty($variables);
        Collections::strings($variables);
        $width = RetrievedQuery::width($query);
        if ($width !== null && $width !== count($variables)) {
            throw new InvalidStructure('SELECT ... INTO requires one variable per selected column.');
        }
        parent::__construct($origin);
    }

    /**
     * Returns the fixed statement category.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Select;
    }

    /**
     * Retains the operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->query, $this->variables);
    }

    /**
     * Replaces the receiving user variables.
     * @param non-empty-list<string> $variables
     * @throws InvalidStructure
     */
    public function withVariables(array $variables): self
    {
        return $this->changed(new self($this->origin, $this->query, $variables));
    }

    /**
     * Replaces the query whose row is stored.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $query, $this->variables));
    }
}
