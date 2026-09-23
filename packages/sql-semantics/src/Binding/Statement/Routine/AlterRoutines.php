<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Classifies named function and procedure alterations before binding their characteristic changes.
 * @visibility SqlSemantics
 */
final class AlterRoutines
{
    /**
     * Keeps target routines separate from language, access, security, and comment changes.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): AlterFunctionStatement|AlterProcedureStatement|null
    {
        $tokens = $source->tokens();
        $kind = strtoupper($tokens[1]->text ?? '');
        if ($origin->dialect !== Dialect::MySql || strtoupper($tokens[0]->text ?? '') !== 'ALTER' || !in_array($kind, ['FUNCTION', 'PROCEDURE'], true)) {
            return null;
        }
        $name = Tree::child($source, ['sp_name']) ?? throw new UnclassifiedSql('A routine alteration requires its name.');
        $parts = $context->tables->identifiers->parts($name);
        if (in_array('', $parts, true)) {
            throw new InvalidSql(InputViolation::RoutineName, $name);
        }
        $target = new QualifiedName($parts);
        $changes = CharacteristicBinder::bind($source, $context->tables->identifiers);
        return $kind === 'FUNCTION' ? new AlterFunctionStatement($origin, $target, $changes) : new AlterProcedureStatement($origin, $target, $changes);
    }
}
