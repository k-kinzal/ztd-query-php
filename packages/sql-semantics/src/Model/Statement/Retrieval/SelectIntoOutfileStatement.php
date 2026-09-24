<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Retrieval;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Loading\FieldLayout;
use SqlSemantics\Model\Statement\Loading\LineLayout;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL `SELECT ... INTO OUTFILE 'file'`: the query's rows are written to a new server file as delimited text instead of being returned.
 *
 * @visibility public
 * @example Reading the export file and separators
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT a INTO OUTFILE '/tmp/a.csv' CHARACTER SET utf8mb4 FIELDS TERMINATED BY ',' FROM t");
 *     [$statement->file->text, $statement->characterSet, $statement->fields->terminator?->text] // => ["'/tmp/a.csv'", 'utf8mb4', "','"]
 */
final class SelectIntoOutfileStatement extends BoundStatement
{
    /**
     * @param Literal $file Server file path as a string literal
     * @param string|null $characterSet Character set of the written text; null uses the server default
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly BoundQuery $query,
        public readonly Literal $file,
        public readonly ?string $characterSet = null,
        public readonly FieldLayout $fields = new FieldLayout(),
        public readonly LineLayout $lines = new LineLayout(),
    ) {
        RetrievedQuery::check($origin, $query, Dialect::MySql);
        if ($file->literalKind !== LiteralKind::Text || $characterSet === '') {
            throw new InvalidStructure('INTO OUTFILE requires a string file name and a nonempty character set name.');
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
        return new static($origin, $this->query, $this->file, $this->characterSet, $this->fields, $this->lines);
    }

    /**
     * Replaces the field and line separators of the written text.
     * @throws InvalidStructure
     */
    public function withLayout(FieldLayout $fields, LineLayout $lines): self
    {
        return $this->changed(new self($this->origin, $this->query, $this->file, $this->characterSet, $fields, $lines));
    }

    /**
     * Replaces the query whose rows are written.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $query, $this->file, $this->characterSet, $this->fields, $this->lines));
    }
}
