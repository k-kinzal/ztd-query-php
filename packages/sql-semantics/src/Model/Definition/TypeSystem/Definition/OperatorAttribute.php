<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

/**
 * An attribute of CREATE OPERATOR and ALTER OPERATOR ... SET.
 * @visibility public
 * @example Reading the argument form of the commutator
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute::Commutator->kind() // => \SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind::Operator
 */
enum OperatorAttribute: string implements DefinitionAttribute
{
    case Function = 'FUNCTION';
    case LeftArg = 'LEFTARG';
    case RightArg = 'RIGHTARG';
    case Commutator = 'COMMUTATOR';
    case Negator = 'NEGATOR';
    case Restrict = 'RESTRICT';
    case Join = 'JOIN';
    case Hashes = 'HASHES';
    case Merges = 'MERGES';

    /**
     * The attribute name as written in SQL.
     */
    public function spelling(): string
    {
        return $this->value;
    }

    /**
     * Functions and estimators are names, operands are types, partners are operators, and capabilities are Boolean.
     */
    public function kind(): DefinitionKind
    {
        return match ($this) {
            self::Function, self::Restrict, self::Join => DefinitionKind::Name,
            self::LeftArg, self::RightArg => DefinitionKind::Type,
            self::Commutator, self::Negator => DefinitionKind::Operator,
            self::Hashes, self::Merges => DefinitionKind::Boolean,
        };
    }

    /**
     * Operator attributes have no keyword choices.
     */
    public function choose(string $text): ?string
    {
        return null;
    }

    /**
     * Only the estimators, partners, and capabilities can change after creation.
     */
    public function alterable(): bool
    {
        return !in_array($this, [self::Function, self::LeftArg, self::RightArg], true);
    }
}
