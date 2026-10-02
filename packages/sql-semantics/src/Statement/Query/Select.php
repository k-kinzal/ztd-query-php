<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Query;

use SqlSemantics\Statement\Expression\ScalarExpression;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Projection\Field;
use SqlSemantics\Statement\Projection\Fields;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;
use UnexpectedValueException;

/**
 * A projection over named relation occurrences, with an optional row predicate.
 * @visibility public
 * @example Creating a separate query from explicit new inputs
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')));
 *     $definition = new \SqlSemantics\Statement\Construction\Query\SelectDefinition(new \SqlSemantics\Statement\Construction\Query\ProjectionDefinition(new \SqlSemantics\Statement\Construction\Query\FieldDefinition(new \SqlSemantics\Statement\Expression\NullConstant())));
 *     (new \SqlSemantics\Statement\Query\Select($catalog, $definition))->toString() // => 'SELECT NULL'
 */
final class Select implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The namespace shared by this query's input references and projection.
     */
    public readonly Scope $scope;

    /**
     * The checked projection of the fresh query.
     */
    private readonly Fields $projection;
    /**
     * The row selection derived in this query's scope.
     */
    public readonly ?ScalarExpression $where;
    /**
     * Requested duplicate handling.
     */
    public readonly Quantifier $quantifier;
    /**
     * Count and offset evaluated in their independent expression scope.
     */
    public readonly ?SqliteLimit $limit;

    /**
     * Derives a new root from complete explicit inputs; no bound field is accepted.
     */
    public function __construct(\SqlSemantics\Statement\Schema\Catalog $context, \SqlSemantics\Statement\Construction\Query\SelectDefinition $definition)
    {
        $data = new \SqlSemantics\Statement\Construction\SelectSnapshot($context, $definition);
        $this->scope = $data->scope;
        $this->projection = $data->projection;
        $this->where = $data->where;
        $this->quantifier = $data->quantifier;
        $this->limit = $data->limit;
    }

    /**
     * Returns the immutable collection of projected expressions and labels.
     */
    public function fields(): Fields
    {
        return $this->projection;
    }

    /**
     * Returns the exact immutable declaration snapshot used by this query.
     */
    public function context(): \SqlSemantics\Statement\Schema\Catalog
    {
        return $this->scope->catalog;
    }

    /**
     * Reads the fixed language profile of this query's declaration context.
     */
    public function profile(): \SqlSemantics\Statement\Contract\LanguageProfile
    {
        return $this->scope->catalog->profile;
    }

    /**
     * Returns a uniquely named result position using the query's identifier rules.
     */
    public function field(string $name): Field
    {
        return $this->projection->field($name);
    }

    /**
     * Reads output lookup without discarding ambiguity or absence.
     */
    public function lookupField(string $name): \SqlSemantics\Statement\Projection\UniqueField|\SqlSemantics\Statement\Projection\AbsentField|\SqlSemantics\Statement\Projection\AmbiguousFields
    {
        return $this->projection->lookupField($name);
    }

    /**
     * Requires exactly one simple named input; never chooses a first input silently.
     * @throws UnexpectedValueException
     */
    public function singleNamedInput(): TableReference
    {
        if (count($this->scope->tables) !== 1) {
            throw new UnexpectedValueException('This query does not have exactly one named input.');
        }
        return $this->scope->tables[0];
    }

    /**
     * Reconstructs SELECT from its projection, inputs, and row selection.
     */
    public function toString(): string
    {
        $sql = 'SELECT' . ($this->quantifier === Quantifier::Default ? '' : ' ' . $this->quantifier->value) . ($this->projection->items === [] ? '' : ' ' . $this->projection->toString());
        $sql .= $this->scope->tables === [] ? '' : ' FROM ' . implode(', ', array_map(static fn (TableReference $table): string => $table->toString(), $this->scope->tables));
        $sql .= $this->where === null ? '' : ' WHERE ' . $this->where->toString();
        return $sql . ($this->limit === null ? '' : ' ' . $this->limit->toString());
    }
}
