<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Maintenance\MySql;

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
 * A server-produced status field for a table-maintenance request.
 * @visibility public
 * @example Inspecting a produced field
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CHECK TABLE t');
 *     $statement->resultColumns()[0]->expression->field->value // => 'Table'
 */
final class StatusColumn extends Expression
{
    /**
     * Derives field types from their role without executing the maintenance request.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $scopeId, public readonly StatusField $field)
    {
        if ($scopeId === '') {
            throw new InvalidStructure('A maintenance result requires its producing statement identity.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'varchar'), Nullability::NotNull), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::MaintenanceStatus;
    }

    /**
     * @return list<Expression> The field is supplied by the server, without scalar SQL inputs
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * Returns the field label, which is not a standalone scalar expression.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->field->value;
    }

    /**
     * Keeps the facts derived from the result role.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Maintenance result facts are derived from their field role.');
        }
        return new self($this->source, $this->scopeId, $this->field);
    }
}
