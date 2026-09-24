<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\View;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\View\MySqlViewProperties;
use SqlSemantics\Model\Definition\ViewCheck;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Requests MySQL ALTER VIEW, replacing the query and declaration properties of an existing view.
 * @visibility public
 * @example Inspecting the replacement query and privilege context
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER SQL SECURITY INVOKER VIEW v (a) AS SELECT 1');
 *     [$statement->name->parts, $statement->columns, $statement->properties->security->value, count($statement->query->outputs)] // => [['v'], ['a'], 'INVOKER', 1]
 */
final class AlterViewStatement extends BoundStatement
{
    /**
     * An omitted algorithm is UNDEFINED and an omitted privilege context is DEFINER, as in the server.
     * @param list<string> $columns Declared result names
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly QualifiedName $name,
        public readonly BoundQuery $query,
        public readonly array $columns = [],
        public readonly ViewCheck $check = ViewCheck::None,
        public readonly MySqlViewProperties $properties = new MySqlViewProperties(),
    ) {
        Collections::strings($columns);
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('ALTER VIEW with view properties requires MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Alter;
    }

    /**
     * Retains the alteration while replacing diagnostic provenance.
     * @throws InvalidStructure
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->query, $this->columns, $this->check, $this->properties);
    }

    /**
     * Replaces the altered view.
     * @throws InvalidStructure
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->query, $this->columns, $this->check, $this->properties));
    }

    /**
     * Replaces the view query and revalidates it against the schema.
     * @throws InvalidStructure
     */
    public function withQuery(BoundQuery $query): self
    {
        return $this->changed(new self($this->origin, $this->name, $query, $this->columns, $this->check, $this->properties));
    }

    /**
     * Replaces the check option.
     * @throws InvalidStructure
     */
    public function withCheck(ViewCheck $check): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->query, $this->columns, $check, $this->properties));
    }

    /**
     * Replaces the algorithm, definer, and privilege context.
     * @throws InvalidStructure
     */
    public function withProperties(MySqlViewProperties $properties): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->query, $this->columns, $this->check, $properties));
    }
}
