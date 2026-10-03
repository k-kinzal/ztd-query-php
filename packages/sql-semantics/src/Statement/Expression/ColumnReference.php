<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\AmbiguousColumn;
use SqlSemantics\Statement\Reference\AmbiguousTable;
use SqlSemantics\Statement\Reference\CandidateColumn;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Reference\NamedAlias;
use SqlSemantics\Statement\Reference\ResolvedColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\NullDomain;
use SqlSemantics\Statement\Type\SqliteChoiceDomain;
use SqlSemantics\Statement\Type\SqliteNumericDomain;
use SqlSemantics\Statement\Type\Unresolved;

/**
 * A column expression whose ownership and type are derived from its immutable scope.
 * @visibility public
 * @example Retaining a column qualifier
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $column = new \SqlSemantics\Statement\Expression\ColumnReference(new \SqlSemantics\Statement\Relation\Scope($catalog), new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('u')));
 *     $column->toString() // => 'u.id'
 */
final class ColumnReference implements ScalarExpression
{
    /**
     * The lookup outcome; callers cannot supply a conflicting declaration or type.
     */
    public readonly ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn|AmbiguousTable|NamedAlias $resolution;

    /**
     * The query containing this lookup site.
     */
    public readonly Scope $scope;

    /**
     * Resolves against the exact supplied scope without modifying its declarations.
     */
    public function __construct(Scope|SqliteAliasScope $scope, public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
        $this->scope = $scope instanceof SqliteAliasScope ? $scope->scope : $scope;
        $this->resolution = $scope->resolve($name, $qualifier);
    }

    /**
     * Reads declared type identity or the concrete reason lookup could not establish it.
     */
    public function type(): TypeDescriptor|Unresolved|Invalid|NullDomain|SqliteNumericDomain|SqliteChoiceDomain
    {
        return match (true) {
            $this->resolution instanceof NamedAlias => $this->resolution->field->expression->type(),
            $this->resolution instanceof ResolvedColumn => $this->resolution->column->type,
            $this->resolution instanceof CandidateColumn => Unresolved::MissingDeclaration,
            $this->resolution instanceof AmbiguousColumn => Invalid::AmbiguousColumn,
            $this->resolution instanceof AmbiguousTable => Invalid::AmbiguousTable,
            $this->resolution instanceof MissingColumn => Invalid::MissingColumn,
        };
    }

    /**
     * Reads the declaration's NULL fact when a unique column is known.
     */
    public function nullability(): Nullability
    {
        return match (true) {
            $this->resolution instanceof NamedAlias => $this->resolution->field->expression->nullability(),
            $this->resolution instanceof ResolvedColumn => $this->resolution->column->nullability,
            default => Nullability::Unknown,
        };
    }

    /**
     * Exposes this lookup for enclosing operation ownership checks.
     * @return list<ColumnReference>
     */
    public function references(): array
    {
        return [$this];
    }

    /**
     * Writes the reference name, independently of declaration storage or parser syntax.
     */
    public function toString(): string
    {
        return ($this->qualifier === null ? '' : $this->qualifier->toString() . '.') . $this->name->toString();
    }
}
