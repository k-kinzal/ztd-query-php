<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Table;

use SqlSemantics\Model\Definition\Relation\Foreign\TableTemplate;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A LIKE clause of a table declaration and where it stands among the declared columns, which fixes the order of the copied columns.
 *
 * @visibility public
 * @example Reading a template between declared columns
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE src(a INTEGER)'));
 *     $statement = $binder->bind('CREATE TABLE t (id INTEGER, LIKE src INCLUDING DEFAULTS, note TEXT)');
 *     $statement->templates[0]->template->source->parts // => ['src']
 *     $statement->templates[0]->position // => 1
 * @example Rejecting a negative position
 *     new \SqlSemantics\Model\Definition\Table\TemplatePlacement(new \SqlSemantics\Model\Definition\Relation\Foreign\TableTemplate(new \SqlSemantics\Model\Relation\QualifiedName(['src'])), -1); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TemplatePlacement
{
    /**
     * The position counts the declared columns written before the LIKE clause.
     *
     * @throws InvalidStructure
     */
    public function __construct(public readonly TableTemplate $template, public readonly int $position)
    {
        if ($position < 0) {
            throw new InvalidStructure('A template position counts preceding columns and cannot be negative.');
        }
    }
}
