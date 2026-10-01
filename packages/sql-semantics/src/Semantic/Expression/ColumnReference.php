<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Reference\AmbiguousColumn;
use SqlSemantics\Semantic\Reference\CandidateColumn;
use SqlSemantics\Semantic\Reference\MissingColumn;
use SqlSemantics\Semantic\Reference\ResolvedColumn;
use SqlSemantics\Semantic\Scope;
use SqlSemantics\Semantic\Type\InvalidReference;
use SqlSemantics\Semantic\Type\Undetermined;
use SqlSemantics\Semantic\Type\UnknownReason;

/**
 * A column lookup whose binding is derived, never supplied independently.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT b.foo FROM bar b');
 *     $statement->field('foo')->expression->qualifier->name->value // => 'b'
 *
 * @visibility public
 */
final class ColumnReference
{
    /**
     * The name-resolution result derived from the owning scope.
     */
    public readonly ResolvedColumn|CandidateColumn|MissingColumn|AmbiguousColumn $binding;
    /**
     * The result type or the explicit reason no type can be established.
     */
    public readonly TypeDescriptor|Undetermined|InvalidReference $type;
    /**
     * The conservative NULL fact at this evaluation stage.
     */
    public readonly Nullability $nullability;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Scope $scope, public readonly Name $name, public readonly ?QualifiedName $qualifier = null)
    {
        $this->binding = $scope->resolve($name, $qualifier);
        $this->type = match (true) {
            $this->binding instanceof ResolvedColumn => $this->binding->column->type,
            $this->binding instanceof CandidateColumn => new Undetermined(UnknownReason::CatalogNotSupplied),
            $this->binding instanceof MissingColumn => new InvalidReference('missing-column'),
            $this->binding instanceof AmbiguousColumn => new InvalidReference('ambiguous-column'),
        };
        $this->nullability = $this->binding instanceof ResolvedColumn
            ? (in_array($this->binding->relation, $scope->nullableRelations, true) ? Nullability::MaybeNull : $this->binding->column->nullability)
            : Nullability::Unknown;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return ($this->qualifier === null ? '' : $this->qualifier->toString() . '.') . $this->name->toString();
    }
}
