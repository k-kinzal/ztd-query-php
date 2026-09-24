<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection;

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
 * A metadata result field with its producing statement identity; the server supplies the value.
 * @visibility public
 * @example Inspecting a SHOW DATABASES result field
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW DATABASES');
 *     $statement->resultColumns()[0]->expression->field === \SqlSemantics\Model\Query\Inspection\Field\Schema\DatabaseField::Name // => true
 */
final class MetadataColumn extends Expression
{
    /**
     * The result label the server uses; a request may extend the field's default label with its operands.
     */
    public readonly string $label;

    /**
     * Type and NULL facts come from the field's closed domain.
     * @param string|null $label Result label replacing the field's default label; null keeps the default
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly MetadataField $field, ?string $label = null)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A metadata result field requires its producing statement identity.');
        }
        if ($label === '') {
            throw new InvalidStructure('A metadata result label requires at least one character.');
        }
        $this->label = $label ?? $field->label();
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, $field->type()), $field->nullability()), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::ServerMetadata;
    }

    /**
     * @return list<Expression> No scalar inputs; the server supplies the metadata
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * Returns the result label, the name a WHERE restriction refers to the field by.
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
            throw new InvalidStructure('Metadata result facts are derived from their field role.');
        }
        return new self($this->source, $this->scopeId, $this->field, $this->label);
    }
}
