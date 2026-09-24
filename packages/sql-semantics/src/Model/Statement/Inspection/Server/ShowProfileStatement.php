<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\ProfileField;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileLimit;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reports the execution stages of one profiled statement, with the requested resource categories.
 * @visibility public
 * @example Inspecting a category request
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROFILE CPU, SWAPS FOR QUERY 7');
 *     [array_column($statement->categories, 'value'), $statement->query->text, array_column($statement->resultColumns(), 'name')] // => [['CPU', 'SWAPS'], '7', ['Status', 'Duration', 'CPU_user', 'CPU_system', 'Swaps']]
 */
final class ShowProfileStatement extends InspectionStatement
{
    /**
     * @param list<ProfileCategory> $categories Requested categories in request order; none reports stage durations only
     * @param Literal|null $query Profiled query number; null selects the most recent statement
     * @param ProfileLimit|null $limit Row window; null returns every stage
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $categories = [], public readonly ?Literal $query = null, public readonly ?ProfileLimit $limit = null)
    {
        Collections::objects($categories, ProfileCategory::class);
        if ($query !== null && ($query->literalKind !== LiteralKind::Number || $query->type->dialect !== Dialect::MySql)) {
            throw new InvalidStructure('A profiled query is selected by a MySQL numeric literal.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->categories, $this->query, $this->limit);
    }

    /**
     * Replaces the requested categories.
     * @param list<ProfileCategory> $categories
     */
    public function withCategories(array $categories): self
    {
        return $this->changed(new self($this->origin, $categories, $this->query, $this->limit));
    }

    /**
     * Selects another profiled query; null selects the most recent statement.
     */
    public function withQuery(?Literal $query): self
    {
        return $this->changed(new self($this->origin, $this->categories, $query, $this->limit));
    }

    /**
     * Replaces the row window; null returns every stage.
     */
    public function withLimit(?ProfileLimit $limit): self
    {
        return $this->changed(new self($this->origin, $this->categories, $this->query, $limit));
    }

    /**
     * @return list<OutputColumn> Stage and duration fields followed by the fields of each measured category
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(ProfileField::detail($this->categories));
    }
}
