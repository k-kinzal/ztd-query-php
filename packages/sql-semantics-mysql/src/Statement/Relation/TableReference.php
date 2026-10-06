<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\LeadingDot;
use SqlSemantics\Platform\MySql\Rules\Query\TableShapes;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Name\AliasMark;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint;
use SqlSemantics\Platform\MySql\Statement\Relation\Hint\TableSample;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * One occurrence of a named table, view or common table as query input, with its partition selection, index hints and sampling.
 *
 * Rule: MYSQL-TABLE-REFERENCE-001. The name resolves by
 * MYSQL-TABLE-SHAPES-001. Partition and index names are not checked: the
 * analysis context holds neither. The sampling percentage is derived by the
 * FROM clause that holds the occurrence. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/join.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-selection.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a named input
 *     $input = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM shop.t AS x')->singleNamedInput();
 *     [$input->name()->schema?->value, $input->name()->name->value, $input->alias()?->value] // => ['shop', 't', 'x']
 * @example Reading the partitions of a table
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t PARTITION (p0, p1)');
 *     count($query->statement->from->partitions) // => 2
 */
final class TableReference implements NamedRelation
{
    use Snapshot;

    /**
     * @var list<Name> The selected partitions in written order; empty for every partition
     */
    public readonly array $partitions;

    /**
     * @var list<IndexHint> The index hints in written order
     */
    public readonly array $indexHints;

    /**
     * @param QualifiedName $name The table name with its optional database
     * @param Name|null $alias The correlation name
     * @param list<Name> $partitions The selected partitions
     * @param list<IndexHint> $indexHints The index hints
     * @param TableSample|null $sample The sampling clause
     * @param AliasMark $mark What is written before the alias
     * @param OptionalWords $dot Whether the name is written after a leading dot, `.t` (MySQL 5.6 and 5.7)
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?Name $alias = null, array $partitions = [], array $indexHints = [], public readonly ?TableSample $sample = null, public readonly AliasMark $mark = AliasMark::As, public readonly OptionalWords $dot = OptionalWords::Omitted)
    {
        Check::input($dot === OptionalWords::Omitted || $name->schema === null, 'Only a table name without database is written after a leading dot.');
        Check::input($alias !== null || $mark === AliasMark::As, 'A relation without alias has no alias mark.');
        Check::input($name->catalog === null, 'A table is qualified by at most a database.');
        $this->partitions = Check::listOf($partitions, Name::class, 'A partition selection names partitions.');
        $this->indexHints = Check::listOf($indexHints, IndexHint::class, 'The index hints of a table are index hints.');
    }

    /**
     * Answers the table name.
     */
    public function name(): QualifiedName
    {
        return $this->name;
    }

    /**
     * Answers the correlation name.
     */
    public function alias(): ?Name
    {
        return $this->alias;
    }

    /**
     * Resolves the name, derives the sampling percentage and the row shape of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        if ($this->sample !== null) {
            $derivation->scalar($this->sample->percentage, new Environment($derivation->context, $environment));
        }

        return (new TableShapes())->named($this->name, $derivation, $environment);
    }

    /**
     * Writes the name, the partitions, the correlation name, the index hints and the sampling clause.
     */
    public function render(Output $out): void
    {
        if ($this->dot === OptionalWords::Written) {
            (new LeadingDot())->write($out);
        }
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
        if ($this->partitions !== []) {
            $out->keyword('PARTITION')->symbol('(');
            foreach ($this->partitions as $position => $partition) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($partition, NameUse::Alias);
            }
            $out->symbol(')');
        }
        if ($this->alias !== null) {
            $this->mark->write($out);
            $out->name($this->alias, NameUse::Alias);
        }
        foreach ($this->indexHints as $hint) {
            $out->node($hint);
        }
        $out->node($this->sample);
    }
}
