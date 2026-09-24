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
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Defines univariate statistics on one expression of a table; this form accepts no statistics kinds.
 * @visibility public
 * @example Reading the expression
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE STATISTICS s ON (a + 1) FROM t');
 *     $statement->expression->kind->value // => 'operator'
 * @example Rejecting a plain column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b TEXT)');
 *     $columns = (new \SqlSemantics\Binder($schema))->bind('CREATE STATISTICS s ON a, b FROM t');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE STATISTICS s ON (a + 1) FROM t');
 *     $statement->withExpression($columns->elements[0]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateExpressionStatisticsStatement extends BoundStatement
{
    /**
     * @param QualifiedName|null $name Statistics object name; null lets the server choose one
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly ?QualifiedName $name, public readonly bool $ifNotExists, public readonly Expression $expression, public readonly NamedTableReference $table)
    {
        StatisticsInvariant::dialect($origin);
        StatisticsInvariant::name($name, $ifNotExists);
        StatisticsInvariant::elements([$expression]);
        if (StatisticsInvariant::column($expression)) {
            throw new InvalidStructure('Statistics on a single column are collected by ANALYZE, not by an extended statistics object.');
        }
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
        return new self($origin, $this->name, $this->ifNotExists, $this->expression, $this->table);
    }

    /**
     * Replaces the statistics object name; null lets the server choose one.
     */
    public function withName(?QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->ifNotExists, $this->expression, $this->table));
    }

    /**
     * Replaces whether an existing statistics object of this name is accepted.
     */
    public function withIfNotExists(bool $ifNotExists): self
    {
        return $this->changed(new self($this->origin, $this->name, $ifNotExists, $this->expression, $this->table));
    }

    /**
     * Replaces the expression whose statistics are collected.
     */
    public function withExpression(Expression $expression): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->ifNotExists, $expression, $this->table));
    }
}
