<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Trigger\RowVersion;

/**
 * A column read from a trigger's OLD or NEW row image, without reading its runtime value.
 * @visibility public
  * @example Inspecting TriggerColumn
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
 *     $step = $statement->body->steps[0];
 *     $step->writes[0]->value instanceof \SqlSemantics\Model\Scalar\Reference\TriggerColumn // => true
 */
final class TriggerColumn extends Expression
{
    /**
     * @visibility SqlSemantics
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly ColumnBinding $binding, public readonly RowVersion $version)
    {
        parent::__construct($facts, $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::TriggerColumn;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function spelling(): ?string
    {
        return null;
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->binding, $this->version);
    }

    /**
     * Returns the resolved relation occurrence and declared column symbol.
     */
    #[Override]
    public function columnBinding(): ColumnBinding
    {
        return $this->binding;
    }

    /**
     * @return non-empty-list<string>
     * @visibility SqlSemantics
     */
    #[Override]
    public function referenceParts(): array
    {
        return [$this->version->value, $this->binding->column->name];
    }
}
