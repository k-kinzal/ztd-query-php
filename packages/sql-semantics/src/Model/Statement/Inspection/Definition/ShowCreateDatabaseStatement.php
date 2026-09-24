<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Definition;

use Override;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Definition\CreateDatabaseField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the CREATE DATABASE statement that would recreate a database, optionally written with IF NOT EXISTS.
 * @visibility public
 * @example Inspecting the described database
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW CREATE SCHEMA IF NOT EXISTS app');
 *     [$statement->database, $statement->ifNotExists] // => ['app', true]
 */
final class ShowCreateDatabaseStatement extends InspectionStatement
{
    /**
     * @param string $database Described database name
     * @param bool $ifNotExists Whether the returned definition includes IF NOT EXISTS
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $database, public readonly bool $ifNotExists = false)
    {
        if ($database === '') {
            throw new InvalidStructure('A database name requires at least one character.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->database, $this->ifNotExists);
    }

    /**
     * Describes another database.
     */
    public function withDatabase(string $database): self
    {
        return $this->changed(new self($this->origin, $database, $this->ifNotExists));
    }

    /**
     * Requests or omits IF NOT EXISTS in the returned definition.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->database, $ifNotExists));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(CreateDatabaseField::cases());
    }
}
