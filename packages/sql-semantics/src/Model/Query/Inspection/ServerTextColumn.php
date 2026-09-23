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
 * A classified server metadata field with its producing statement identity.
 * @visibility public
 * @example Inspecting a nullable plugin library
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PLUGINS');
 *     $statement->resultColumns()[3]->expression->field === \SqlSemantics\Model\Query\Inspection\PluginField::Library // => true
 */
final class ServerTextColumn extends Expression
{
    /**
     * Field names and NULL facts come from a closed server-metadata domain.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly EngineField|PluginField|PrivilegeField $field)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A server metadata field requires its producing statement identity.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'varchar'), $field->nullability()), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::ServerMetadata;
    }

    /**
     * @return list<Expression> The server supplies the metadata at execution
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
        return $this->field->value;
    }

    /**
     * Preserves the facts derived from the metadata field's role.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Server metadata facts are derived from their field role.');
        }
        return new self($this->source, $this->scopeId, $this->field);
    }
}
