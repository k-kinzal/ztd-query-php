<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Statistics;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\NamedTableReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Defines multivariate statistics over two to eight distinct columns and expressions of one table.
 * An empty kind list requests every statistics kind; a column is a column reference, anything else an expression.
 * @visibility public
 * @example Reading the covered columns
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE STATISTICS s (dependencies) ON a, lower(b) FROM t');
 *     $statement->kinds // => [\SqlSemantics\Model\Statement\Definition\Statistics\StatisticsKind::Dependencies]
 *     $statement->elements[0]->binding->column->name // => 'a'
 *     $statement->table->name->parts // => ['public', 't']
 * @example Rejecting a single column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE STATISTICS s ON a, b FROM t');
 *     $statement->withElements([$statement->elements[0]]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateStatisticsStatement extends BoundStatement
{
    /**
     * @param QualifiedName|null $name Statistics object name; null lets the server choose one
     * @param list<StatisticsKind> $kinds Requested kinds in request order; empty requests all kinds
     * @param non-empty-list<Expression> $elements Covered columns and expressions
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?QualifiedName $name, public readonly bool $ifNotExists, public readonly array $kinds, public readonly array $elements, public readonly NamedTableReference $table)
    {
        StatisticsInvariant::dialect($origin);
        StatisticsInvariant::name($name, $ifNotExists);
        Collections::objects($kinds, StatisticsKind::class);
        if (count(array_unique(array_map(static fn (StatisticsKind $kind): string => $kind->value, $kinds))) !== count($kinds)) {
            throw new InvalidStructure('A statistics kind is requested at most once.');
        }
        Collections::objects(Collections::nonEmpty($elements), Expression::class);
        if (count($elements) < 2) {
            throw new InvalidStructure('Multivariate statistics cover at least two columns or expressions.');
        }
        StatisticsInvariant::elements($elements);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains the definition while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->ifNotExists, $this->kinds, $this->elements, $this->table);
    }

    /**
     * Replaces the statistics object name; null lets the server choose one.
     */
    public function withName(?QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifNotExists, $this->kinds, $this->elements, $this->table));
    }

    /**
     * Replaces whether an existing statistics object of this name is accepted.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifNotExists, $this->kinds, $this->elements, $this->table));
    }

    /**
     * Replaces the requested kinds.
     * @param list<StatisticsKind> $kinds
     */
    public function withKinds(array $kinds): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifNotExists, $kinds, $this->elements, $this->table));
    }

    /**
     * Replaces the covered columns and expressions.
     * @param non-empty-list<Expression> $elements
     */
    public function withElements(array $elements): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifNotExists, $this->kinds, $elements, $this->table));
    }
}
