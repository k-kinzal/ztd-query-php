<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

use InvalidArgumentException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Schema\Invariant;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * A typed expression with original syntax, occurrence-aware lineage, and NULL provenance.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Model\Expression $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class Expression
{
    /**
     * @param ExpressionKind $kind Semantic operation
     * @param TypeDescriptor $type Result type
     * @param Nullability $nullability Conservative NULL fact at this evaluation stage
     * @param Node|Token $source Original syntax object, never a reparsed copy
     * @param list<Expression> $operands Ordered inputs to the operation
     * @param ColumnBinding|null $binding Resolved declaration; exactly a column reference has one
     * @param Operator|null $operator Applied operator; exactly an operator expression has one
     * @param string|null $symbol Literal or parameter spelling; exactly those kinds have one
     * @param list<string> $nullExtendedBy Join IDs that can introduce NULL into this result
     * @throws InvalidArgumentException When the fields do not match the kind
     */
    public function __construct(
        public readonly ExpressionKind $kind,
        public readonly TypeDescriptor $type,
        public readonly Nullability $nullability,
        public readonly Node|Token $source,
        public readonly array $operands = [],
        public readonly ?ColumnBinding $binding = null,
        public readonly ?Operator $operator = null,
        public readonly ?string $symbol = null,
        public readonly array $nullExtendedBy = [],
    ) {
        Invariant::members($operands, self::class);
        Invariant::names($nullExtendedBy);
        Invariant::ensure(($kind === ExpressionKind::Column) === ($binding !== null), 'Exactly a column reference has a binding.');
        Invariant::ensure(($kind === ExpressionKind::Operator) === ($operator !== null), 'Exactly an operator expression has an operator.');
        Invariant::ensure(in_array($kind, [ExpressionKind::Literal, ExpressionKind::Parameter], true) === ($symbol !== null), 'Exactly literals and parameters have a spelling.');
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
}
