<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Retrieval;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL `SELECT ... INTO DUMPFILE 'file'`: the query's single row is written to a new server file without separators or escaping.
 *
 * @visibility public
 * @example Reading the dump file
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a BLOB)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT a FROM t INTO DUMPFILE '/tmp/a.bin'");
 *     $statement->file->text // => "'/tmp/a.bin'"
 */
final class SelectIntoDumpfileStatement extends BoundStatement
{
    /**
     * @param Literal $file Server file path as a string literal
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly BoundQuery $query, public readonly Literal $file)
    {
        RetrievedQuery::check($origin, $query, Dialect::MySql);
        if ($file->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('INTO DUMPFILE requires a string file name.');
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
        return new static($origin, $this->query, $this->file);
    }

    /**
     * Replaces the query whose row is written.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $query, $this->file));
    }
}
