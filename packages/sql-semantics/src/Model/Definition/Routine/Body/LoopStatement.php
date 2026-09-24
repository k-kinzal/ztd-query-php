<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * LOOP ... END LOOP: repeats its statements until LEAVE or RETURN exits.
 * @visibility public
 * @example Reading a labeled loop
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() spin: LOOP LEAVE spin; END LOOP spin');
 *     $statement->body->label // => 'spin'
 *     $statement->body->statements[0]->label // => 'spin'
 */
final class LoopStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<ProgramStatement>
     */
    public readonly array $statements;

    /**
     * Requires at least one statement and a nonempty label when labeled.
     * @param list<ProgramStatement> $statements
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $label, array $statements)
    {
        Collections::objects($statements, ProgramStatement::class);
        if ($label === '') {
            throw new InvalidStructure('A loop label requires a name.');
        }
        $this->statements = Collections::nonEmpty($statements);
    }
}
