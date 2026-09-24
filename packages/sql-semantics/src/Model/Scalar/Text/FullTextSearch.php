<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A MySQL MATCH ... AGAINST relevance search over indexed columns, without running the search.
 * @visibility public
 * @example Inspecting the searched columns and the search mode
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (title TEXT, body TEXT)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT MATCH (title, body) AGAINST ('word' IN BOOLEAN MODE) FROM t");
 *     $search = $query->outputs[0]->expression;
 *     count($search->columns) // => 2
 *     $search->query->spelling() // => "'word'"
 *     $search->mode // => \SqlSemantics\Model\Scalar\Text\FullTextMode::Boolean
 */
final class FullTextSearch extends Expression
{
    /**
     * @var non-empty-list<Expression>
     */
    public readonly array $columns;

    /**
     * Derives a non-NULL double relevance from the searched columns and the search string.
     * @param list<Expression> $columns Column references in index order
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, array $columns, public readonly Expression $query, public readonly FullTextMode $mode)
    {
        $this->columns = Collections::nonEmpty($columns);
        if ($query->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MATCH ... AGAINST requires MySQL.');
        }
        foreach ($this->columns as $column) {
            if ($column->type->dialect !== Dialect::MySql || !in_array($column->kind, [ExpressionKind::Column, ExpressionKind::UnresolvedColumn], true)) {
                throw new InvalidStructure('MATCH searches MySQL column references only.');
            }
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'double precision'), Nullability::NotNull), $source);
    }

    /**
     * Identifies a full-text relevance search.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::FullTextSearch;
    }

    /**
     * @return list<Expression> The searched columns followed by the search string
     */
    #[Override]
    public function inputs(): array
    {
        return [...$this->columns, $this->query];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'MATCH';
    }

    /**
     * Preserves the facts derived from the search operands.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Full-text search facts are derived from its operands.');
        }
        return new static($this->source, $this->columns, $this->query, $this->mode);
    }
}
