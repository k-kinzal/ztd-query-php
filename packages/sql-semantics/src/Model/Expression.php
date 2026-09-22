<?php

declare(strict_types=1);

namespace SqlSemantics\Model;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A typed expression with original syntax, occurrence-aware lineage, and NULL provenance.
 *
 * @example Reading semantic facts
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE users (id INTEGER PRIMARY KEY, score INTEGER NOT NULL)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT a.id, b.score FROM users a LEFT JOIN users b ON a.id=b.id ORDER BY a.id DESC');
 *     $statement->outputs[1]->expression->nullExtendedBy // => ['j0']
 *
 * @visibility public
 */
final class Expression
{
    /**
     * SQL expression structure, without source formatting.
     */
    public readonly Sql\Tree $sql;

    /**
     * @param ExpressionKind $kind Semantic operation
     * @param TypeDescriptor $type Result type
     * @param Nullability $nullability Conservative NULL fact at this evaluation stage
     * @param Node|Token $source Original syntax object, never a reparsed copy
     * @param list<Expression> $operands Ordered inputs to the operation
     * @param ColumnBinding|null $binding Resolved declaration for a column reference
     * @param string|null $symbol Operator, parameter name, or literal spelling
     * @param list<string> $nullExtendedBy Join IDs that can introduce NULL into this result
     * @param BoundQuery|null $query Bound scalar, EXISTS, or membership subquery
     * @param list<string> $reference Unresolved name parts or wildcard qualifier
     */
    public function __construct(
        public readonly ExpressionKind $kind,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node|Token $source,
        public readonly array $operands = [],
        public readonly ?ColumnBinding $binding = null,
        public readonly ?string $symbol = null,
        public readonly array $nullExtendedBy = [],
        public readonly ?BoundQuery $query = null,
        public readonly array $reference = [],
        ?Sql\Tree $sql = null,
    ) {
        $this->sql = $sql ?? Sql\Source::read($source);
        Validation\ExpressionInvariant::check($this);
    }

    /**
     * Collects value dependencies without collapsing separate uses of the same table.
     *
     * @return list<ColumnBinding> Unique relation-and-column bindings in encounter order
     */
    public function lineage(): array
    {
        $bindings = $this->binding === null ? [] : [$this->binding];
        foreach ($this->operands as $operand) {
            array_push($bindings, ...$operand->lineage());
        }
        $unique = [];
        foreach ($bindings as $binding) {
            $unique[$binding->relationId . ':' . $binding->column->name] = $binding;
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
