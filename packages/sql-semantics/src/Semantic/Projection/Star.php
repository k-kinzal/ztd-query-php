<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Projection;

use SqlSemantics\Semantic\QualifiedName;
use SqlSemantics\Semantic\Scope;

/**
 * Projection of all visible columns, expanded only when declarations exist.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT * FROM bar');
 *     $statement->fields()->items[0]->fields // => null
 *
 * @visibility public
 */
final class Star
{
    /**
     * @var list<Field>|null Null means a catalog is needed to determine the width.
     */
    public readonly ?array $fields;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Scope $scope, public readonly ?QualifiedName $qualifier = null)
    {
        $fields = [];
        foreach ($scope->tables as $table) {
            if (!$scope->matches($table, $qualifier)) {
                continue;
            }
            if (!$table->catalogSupplied) {
                $this->fields = null;
                return;
            }
            foreach ($table->declaration->columns ?? [] as $column) {
                $fields[] = new Field($scope->column($column->name, new QualifiedName($table->visibleName())));
            }
        }
        $this->fields = $fields;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return ($this->qualifier === null ? '' : $this->qualifier->toString() . '.') . '*';
    }
}
