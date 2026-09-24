<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Loading\Copy\Endpoint\CopyEndpoint;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Writes the rows a query returns to a server file, a server program or the client; binding runs neither the query nor the copy.
 * A data-modifying query must return rows with RETURNING.
 * @visibility public
 * @example Reading the query
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('COPY (SELECT a FROM t) TO STDOUT (FORMAT csv)');
 *     [$statement->query->resultColumns()[0]->name, $statement->toString()] // => ['a', 'COPY(SELECT "a" AS "a" FROM "public"."t") TO STDOUT WITH (FORMAT \'csv\')']
 * @example Rejecting a data-modifying query without RETURNING
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT)');
 *     (new \SqlSemantics\Binder($schema))->bind('COPY (DELETE FROM t) TO STDOUT'); // throws \SqlSemantics\InvalidSql
 */
final class CopyQueryStatement extends BoundStatement
{
    /**
     * @param BoundStatement&ResultStatement $query Query or data-modifying statement returning at least one column
     * @param CopyEndpoint $destination Server file, server program or client output
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly BoundStatement&ResultStatement $query,
        public readonly CopyEndpoint $destination,
        public readonly CopyOptions $options = new CopyOptions(),
    ) {
        if ($origin->dialect !== Dialect::PostgreSql || $query->origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('COPY of a query requires PostgreSQL.');
        }
        if ($query->resultColumns() === []) {
            throw new InvalidStructure('A copied query must return columns.');
        }
        CopyOptionRules::writing($options);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Copy;
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->query, $this->destination, $this->options);
    }

    /**
     * Copies the rows of another query.
     */
    public function withQuery(BoundStatement&ResultStatement $query): self
    {
        return $this->changed(new self($this->origin, $query, $this->destination, $this->options));
    }

    /**
     * Writes to another destination.
     */
    public function withDestination(CopyEndpoint $destination): self
    {
        return $this->changed(new self($this->origin, $this->query, $destination, $this->options));
    }

    /**
     * Replaces the options.
     */
    public function withOptions(CopyOptions $options): self
    {
        return $this->changed(new self($this->origin, $this->query, $this->destination, $options));
    }
}
