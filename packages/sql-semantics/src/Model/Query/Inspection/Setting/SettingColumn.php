<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Setting;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A text result field of PostgreSQL SHOW; the server supplies the displayed parameter value at execution.
 * @visibility public
 * @example Inspecting the value column of SHOW
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SHOW work_mem');
 *     $column = $statement->resultColumns()[0]->expression;
 *     [$column->field === \SqlSemantics\Model\Query\Inspection\Setting\SettingField::Setting, $column->label] // => [true, 'work_mem']
 */
final class SettingColumn extends Expression
{
    /**
     * Every field is text; its NULL fact comes from the field role.
     * @param string $label Result label: the requested parameter name, or the field name for SHOW ALL
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly SettingField $field, public readonly string $label)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A setting result field requires its producing statement identity.');
        }
        if ($label === '') {
            throw new InvalidStructure('A setting result label requires at least one character.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), $field->nullability()), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::ServerMetadata;
    }

    /**
     * @return list<Expression> No scalar inputs; the server supplies the parameter data
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * Returns the result label, which has no standalone scalar SQL form.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->label;
    }

    /**
     * Preserves the facts derived from the field role.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Setting result facts are derived from their field role.');
        }
        return new self($this->source, $this->scopeId, $this->field, $this->label);
    }
}
