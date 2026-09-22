<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Extracts one declared calendar or interval field without evaluating its input.
 * @visibility public
 * @example Inspecting a timestamp extraction
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)');
 *     $query->outputs[0]->expression->field->value // => 'YEAR'
 */
final class Extract extends Expression
{
    /**
     * Derives the numeric result from the extraction dialect and operand facts.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly PostgreSqlField|MySqlUnit $field, public readonly Expression $value)
    {
        $dialect = $field instanceof PostgreSqlField ? Dialect::PostgreSql : Dialect::MySql;
        if ($value->type->dialect !== $dialect) {
            throw new InvalidStructure('The extraction field and operand must use the same SQL dialect.');
        }
        $nullable = $value->nullability === Nullability::NotNull ? Nullability::MaybeNull : $value->nullability;
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin($dialect, $dialect === Dialect::PostgreSql ? 'numeric' : 'bigint'), $nullable, $value->nullExtendedBy), $source);
    }

    /**
     * Identifies a field extraction operation.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Extract;
    }

    /**
     * @return list<Expression> The temporal input, without a runtime value
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'EXTRACT';
    }

    /**
     * Keeps the facts derived from the operation's required operands.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Extraction facts are derived from its field and input.');
        }
        return new static($this->source, $this->field, $this->value);
    }
}
