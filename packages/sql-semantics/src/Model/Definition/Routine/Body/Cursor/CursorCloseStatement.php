<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Cursor;

use SqlSemantics\Model\Definition\Routine\Body\ProgramStatement;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * CLOSE cursor: releases an open declared cursor.
 * @visibility public
 * @example Reading the closed cursor
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; CLOSE c; END');
 *     $statement->body->statements[0]->cursor // => 'c'
 */
final class CursorCloseStatement implements ProgramStatement
{
    /**
     * Requires the cursor's name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $cursor)
    {
        if ($cursor === '') {
            throw new InvalidStructure('CLOSE requires a cursor name.');
        }
    }
}
