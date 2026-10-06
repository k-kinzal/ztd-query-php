<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\References;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Derives the facts inside table elements, column specifications and table options, for CREATE TABLE and ALTER TABLE.
 *
 * Rule: MYSQL-ELEMENT-FACTS-001. The expressions of an element are derived
 * at the position the caller gives, whose only visible relation is the
 * table being defined or changed (MYSQL-DEFINITION-SCOPE-001). The parent
 * table of a REFERENCES clause is resolved and recorded as a table use
 * (MYSQL-REFERENCES-001). Table options hold no expression and no
 * resolvable name: the tables of a MERGE UNION option are named, not read,
 * when the table is defined. Terminates: one pass over the element.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-table.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ElementFacts
{
    /**
     * Derives an element; a reference to the table being defined resolves to `$defined` when it is given.
     *
     * @param Table|null $defined The declaration the statement provides for the table being defined
     * @param QualifiedName|null $definedName The name the statement writes for that table
     */
    public function element(TableElement $element, Derivation $derivation, Environment $scope, ?Table $defined = null, ?QualifiedName $definedName = null): void
    {
        $element->deriveElement($derivation, $scope);
        $references = $element instanceof ForeignKey ? $element->references : ($element instanceof ColumnDefinition ? $this->references($element->specification) : null);
        $references?->deriveReferences($derivation, $defined, $definedName);
    }

    /**
     * Derives a column specification held by an ALTER TABLE.
     */
    public function specification(ColumnSpecification $specification, Derivation $derivation, Environment $scope): void
    {
        $specification->deriveSpecification($derivation, $scope);
        $this->references($specification)?->deriveReferences($derivation);
    }

    /**
     * Confirms a list of table options; they hold nothing to derive.
     *
     * @param list<TableOption> $options
     */
    public function options(array $options, Derivation $derivation): void
    {
        Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
    }

    /**
     * Answers the column declaration a column definition requests, as CREATE TABLE declares it without a table-level primary key.
     */
    public function declaration(ColumnDefinition $column): Column
    {
        return (new TableDeclaration())->column($column);
    }

    /**
     * Answers the inline REFERENCES clause of a column specification.
     */
    public function references(ColumnSpecification $specification): ?References
    {
        return $specification instanceof OrdinaryColumn || $specification instanceof GeneratedColumn ? $specification->references : null;
    }
}
