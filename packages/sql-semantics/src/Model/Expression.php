<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * An immutable classified expression. Concrete forms own their required operands.
 * @visibility public
  * @example Inspecting Expression
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(score INTEGER)')))->bind('SELECT app.percentile(0.5) WITHIN GROUP (ORDER BY score DESC NULLS FIRST) FILTER (WHERE score>0) FROM t');
 *     $aggregate = $statement->outputs[0]->expression;
 *     $aggregate->withinGroup[0]->key instanceof \SqlSemantics\Model\Expression // => true
 */
abstract class Expression
{
    /**
     * Expression category derived from its concrete operand shape.
     */
    public readonly ExpressionKind $kind;
    /**
     * Statically derived result type, without a runtime value.
     */
    public readonly TypeDescriptor $type;
    /**
     * Statically derived NULL behavior at this expression occurrence.
     */
    public readonly Nullability $nullability;
    /**
     * @var list<string>
     */
    public readonly array $nullExtendedBy;

    /**

     * @visibility SqlSemantics

     */
    public function __construct(public readonly Scalar\ExpressionFacts $facts, public readonly Node|Token $source)
    {
        $this->kind = $this->operation();
        $this->type = $facts->type;
        $this->nullability = $facts->nullability;
        $this->nullExtendedBy = $facts->nullExtendedBy;
    }

    /**

     * @visibility SqlSemantics

     */
    public function structure(): Sql\Tree
    {
        return \SqlSemantics\Serialization\Expressions::write($this);
    }

    abstract protected function operation(): ExpressionKind;

    /**

     * @return list<Expression>

     */
    abstract public function inputs(): array;

    /**

     * @visibility SqlSemantics

     */
    abstract public function withFacts(Scalar\ExpressionFacts $facts): static;

    /**

     * @visibility SqlSemantics

     */
    abstract public function spelling(): ?string;

    /**

     * @visibility SqlSemantics

     */
    public function columnBinding(): ?ColumnBinding
    {
        return null;
    }

    /**
     * @return list<string>
     * @visibility SqlSemantics
     */
    public function referenceParts(): array
    {
        return [];
    }

    /**

     * @visibility SqlSemantics

     */
    public function subquery(): ?BoundQuery
    {
        return null;
    }

    /**

     * @return list<ColumnBinding>

     */
    public function lineage(): array
    {
        $binding = $this->columnBinding();
        $bindings = $binding === null ? [] : [$binding];
        foreach ($this->inputs() as $operand) {
            array_push($bindings, ...$operand->lineage());
        }
        $unique = [];
        foreach ($bindings as $candidate) {
            $unique[$candidate->relationId . ':' . $candidate->column->name] = $candidate;
        }
        return array_values($unique);
    }

    /**
     * Constructs one value with safe SQL quoting and no original SQL text.
     */
    public static function literal(string|int|float|bool|null $value, \SqlSemantics\Dialect $dialect): self
    {
        return Sql\ExpressionFactory::literal($value, $dialect);
    }

    /**
     * Constructs a name to resolve in the destination statement's scope.
     *
     * @param list<string> $name Identifier parts without SQL quotes
     */
    public static function reference(array $name, \SqlSemantics\Dialect $dialect): self
    {
        return Sql\ExpressionFactory::reference($name, $dialect);
    }

    /**
     * Constructs a binary operation whose facts are bound in its destination statement.
     */
    public static function binary(string $operator, self $left, self $right): self
    {
        return Sql\ExpressionFactory::binary($operator, $left, $right);
    }

}
