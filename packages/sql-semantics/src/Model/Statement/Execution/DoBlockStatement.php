<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Execution;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Runs a PostgreSQL anonymous code block; binding records the code as a text constant and never runs it.
 * Without a language the server uses plpgsql.
 * @visibility public
 * @example Reading the code and its language
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('DO $$BEGIN NULL; END$$ LANGUAGE plpgsql');
 *     [$statement->code->text, $statement->language] // => ['$$BEGIN NULL; END$$', 'plpgsql']
 * @example Rejecting an empty language name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("DO 'x'");
 *     $statement->withLanguage(''); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DoBlockStatement extends BoundStatement
{
    /**
     * @param Literal $code Text constant holding the block source, keeping its written spelling
     * @param string|null $language Procedural language name; null selects the server default
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly Literal $code, public readonly ?string $language = null)
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('An anonymous code block requires PostgreSQL.');
        }
        if ($code->literalKind !== LiteralKind::Text || $code->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Anonymous block code is a PostgreSQL text constant.');
        }
        if ($language === '') {
            throw new InvalidStructure('A block language requires a nonempty name.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Do;
    }

    /**
     * Retains the block while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->code, $this->language);
    }

    /**
     * Replaces the block source.
     */
    public function withCode(Literal $code): self
    {
        return $this->changed(new self($this->origin, $code, $this->language));
    }

    /**
     * Selects the procedural language; null selects the server default.
     */
    public function withLanguage(?string $language): self
    {
        return $this->changed(new self($this->origin, $this->code, $language));
    }
}
