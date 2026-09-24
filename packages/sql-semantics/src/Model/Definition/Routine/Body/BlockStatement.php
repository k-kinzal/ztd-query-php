<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body;

use SqlSemantics\Model\Definition\Routine\Body\Declaration\ConditionDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\CursorDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerDeclaration;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\VariableDeclaration;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A BEGIN ... END compound statement: local declarations visible to its ordered statements.
 * @visibility public
 * @example Reading block declarations
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() work: BEGIN DECLARE x INT; SET x = 1; END work');
 *     $statement->body->label // => 'work'
 *     $statement->body->declarations[0]->names // => ['x']
 */
final class BlockStatement implements ProgramStatement
{
    /**
     * Declarations follow the server order: variables and conditions, then cursors, then handlers, each name once.
     * @param list<VariableDeclaration|ConditionDeclaration|CursorDeclaration|HandlerDeclaration> $declarations
     * @param list<ProgramStatement> $statements
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $label, public readonly array $declarations = [], public readonly array $statements = [])
    {
        Collections::alternatives($declarations, [VariableDeclaration::class, ConditionDeclaration::class, CursorDeclaration::class, HandlerDeclaration::class]);
        Collections::objects($statements, ProgramStatement::class);
        if ($label === '') {
            throw new InvalidStructure('A block label requires a name.');
        }
        Declaration\DeclarationOrder::check($declarations);
    }
}
