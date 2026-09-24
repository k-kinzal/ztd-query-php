<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Loads a PostgreSQL shared library into the session; binding records the file name and never opens it.
 * @visibility public
 * @example Reading the library file
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("LOAD 'auto_explain'");
 *     $statement->file->text // => "'auto_explain'"
 */
final class LoadLibraryStatement extends BoundStatement
{
    /**
     * @param Literal $file Text constant naming the library, keeping its written spelling
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Literal $file)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('LOAD of a shared library requires PostgreSQL.');
        }
        if ($file->literalKind !== LiteralKind::Text || $file->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A library file is a PostgreSQL text constant.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Load;
    }

    /**
     * Retains the library while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->file);
    }

    /**
     * Loads another library file.
     */
    public function withFile(Literal $file): self
    {
        return $this->changed(new self($this->origin, $file));
    }
}
