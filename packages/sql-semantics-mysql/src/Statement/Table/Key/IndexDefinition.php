<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * An index or key of a table definition: INDEX, PRIMARY KEY, UNIQUE, FULLTEXT or SPATIAL with its parts and options.
 *
 * Rule: MYSQL-INDEX-DEFINITION-001. KEY and INDEX are synonyms. After
 * UNIQUE, FULLTEXT and SPATIAL the word INDEX (or KEY) is optional and changes
 * nothing; whether it is written is kept so the statement is written back
 * with the same words. A USING clause can stand before the key parts and
 * among the options; both are kept where written. The name of a PRIMARY KEY
 * is ignored by the server (the primary key is always named PRIMARY). A
 * CONSTRAINT clause is accepted before PRIMARY KEY and UNIQUE only. The
 * functional key parts see the columns of the table (MYSQL-DEFINITION-SCOPE-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html#create-table-indexes-keys,
 * https://dev.mysql.com/doc/refman/8.4/en/create-index.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading an index definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT, UNIQUE KEY ab (a, b))');
 *     [$create->statement->elements[2]->kind, $create->statement->elements[2]->name->column->value, count($create->statement->elements[2]->parts)] // => [\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Unique, 'ab', 2]
 */
final class IndexDefinition implements TableElement
{
    use Snapshot;

    /**
     * @var non-empty-list<KeyPart> The key parts in order
     */
    public readonly array $parts;

    /**
     * @var list<IndexOption> The options after the key parts, in written order
     */
    public readonly array $options;

    /**
     * @param IndexKind $kind The kind of index
     * @param list<KeyPart> $parts The key parts in order; at least one
     * @param ColumnName|null $name The index name, when written
     * @param IndexAlgorithm|null $algorithm The USING clause written before the key parts
     * @param list<IndexOption> $options The options after the key parts, in written order
     * @param ConstraintName|null $constraint The CONSTRAINT clause of a PRIMARY KEY or UNIQUE index
     * @param bool $keyword Whether INDEX or KEY is written after UNIQUE, FULLTEXT or SPATIAL
     */
    public function __construct(
        public readonly IndexKind $kind,
        array $parts,
        public readonly ?ColumnName $name = null,
        public readonly ?IndexAlgorithm $algorithm = null,
        array $options = [],
        public readonly ?ConstraintName $constraint = null,
        public readonly bool $keyword = false,
    ) {
        $this->parts = Check::listOf($parts, KeyPart::class, 'An index has at least one key part.', 1);
        $this->options = Check::listOf($options, IndexOption::class, 'Index options are an ordered list of index options.');
        Check::input($constraint === null || $kind === IndexKind::Primary || $kind === IndexKind::Unique, 'Only a primary key or a unique index has a CONSTRAINT clause.');
        Check::input(!$keyword || $kind === IndexKind::Unique || $kind === IndexKind::FullText || $kind === IndexKind::Spatial, 'Only UNIQUE, FULLTEXT and SPATIAL are followed by an optional INDEX.');
        Check::input($algorithm === null || ($kind !== IndexKind::FullText && $kind !== IndexKind::Spatial), 'A full-text or spatial index has no USING clause before its key parts.');
    }

    /**
     * Derives the functional key parts at the position of the table.
     */
    public function deriveElement(Derivation $derivation, Environment $scope): void
    {
        foreach ($this->parts as $part) {
            $part->deriveKeyPart($derivation, $scope);
        }
    }

    /**
     * Writes the constraint, the kind, the name, the algorithm, the key parts and the options.
     */
    public function render(Output $out): void
    {
        $out->node($this->constraint)->keyword(...explode(' ', $this->kind->value));
        if ($this->keyword) {
            $out->keyword('INDEX');
        }
        $out->node($this->name);
        if ($this->algorithm !== null) {
            $out->keyword('USING', $this->algorithm->value);
        }
        $out->symbol('(')->list($this->parts)->symbol(')');
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
