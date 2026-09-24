<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Cursor;

use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * FETCH [[NEXT] FROM] cursor INTO variables: stores the cursor's next row in local variables.
 * @visibility public
 * @example Reading fetch targets
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE a INT; DECLARE c CURSOR FOR SELECT 1; FETCH NEXT FROM c INTO a; END');
 *     $fetch = $statement->body->statements[0];
 *     [$fetch->cursor, $fetch->targets[0]->variable->name] // => ['c', 'a']
 */
final class CursorFetchStatement implements ProgramStatement
{
    /**
     * @var non-empty-list<LocalVariableReference>
     */
    public readonly array $targets;

    /**
     * Requires the cursor's name and at least one local variable.
     * @param list<LocalVariableReference> $targets
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $cursor, array $targets)
    {
        Collections::objects($targets, LocalVariableReference::class);
        if ($cursor === '') {
            throw new InvalidStructure('FETCH requires a cursor name.');
        }
        $this->targets = Collections::nonEmpty($targets);
    }
}
