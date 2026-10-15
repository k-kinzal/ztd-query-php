<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
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
use SqlSemantics\Statement\Node;

/**
 * Derives the facts inside table elements, column specifications and table options, for CREATE TABLE and ALTER TABLE.
 *
 * Rule: MYSQL-ELEMENT-FACTS-001. The expressions of an element are derived
 * at the position the caller gives, whose only visible relation is the
 * table being defined or changed (MYSQL-DEFINITION-SCOPE-001). The parent
 * table of a REFERENCES clause is resolved and recorded as a table use
 * (MYSQL-REFERENCES-001). Table options resolve character sets and collations
 * in written order. The tables of a MERGE UNION option are named, not read,
 * when the table is defined. Terminates: one pass over the element or option list.
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
     * @param bool $typeNotices Whether ALTER TABLE must report the type notices here
     * @param Table|null $defined The declaration the statement provides for the table being defined
     * @param QualifiedName|null $definedName The name the statement writes for that table
     */
    public function element(TableElement $element, Derivation $derivation, Environment $scope, ?Table $defined = null, ?QualifiedName $definedName = null, bool $typeNotices = false): void
    {
        if ($typeNotices && $element instanceof ColumnDefinition) {
            (new TypeNotices())->type($element->specification->dataType(), $derivation);
        }
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
     * Resolves table character sets and collations in option order, before later clauses.
     *
     * Unknown names stop parsing. These notices use the retained names and option order,
     * so direct construction and SQL lowering produce the same diagnostic sequence.
     *
     * @param list<TableOption> $options
     */
    public function options(array $options, Derivation $derivation): void
    {
        Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
        foreach ($options as $option) {
            $name = match (true) {
                $option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption => $option->collation,
                $option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption => $option->charset,
                default => null,
            };
            if ($name !== null) {
                $this->encoding($name, $derivation, $option);
            }
        }
    }

    /**
     * Resolves a retained character set or collation at its enclosing table action.
     *
     * An unknown name aborts before table resolution, including for CONVERT TO
     * CHARACTER SET. DEFAULT depends on the database and is resolved later.
     */
    public function encoding(CharsetName|CollationName $name, Derivation $derivation, Node $owner): void
    {
        $problem = match (true) {
            $name instanceof CollationName && $name->name !== null && !(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($name->name->value)?->availableIn($derivation->context->profile->grammar) ?? false) => new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation($name->name->value),
            $name instanceof CharsetName && $name->name !== null && \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($name->name->value) === null => new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset($name->name->value),
            default => null,
        };
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $owner);
        }
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
