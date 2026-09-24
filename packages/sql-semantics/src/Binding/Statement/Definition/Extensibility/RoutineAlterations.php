<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Extensibility;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Routine\Targets;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Catalog\Kind\RoutineKind;
use SqlSemantics\Model\Statement\Definition\Routine\AlterRoutineStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds attribute changes of PostgreSQL functions, procedures, and routines.
 * @visibility SqlSemantics
 */
final class RoutineAlterations
{
    /**
     * ALTER { FUNCTION | PROCEDURE | ROUTINE } routine attribute ... [RESTRICT].
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alter(Origin $origin, Node $source, QueryContext $context): AlterRoutineStatement
    {
        $kind = RoutineKind::from(strtoupper($source->tokens()[1]->text ?? ''));
        $target = Targets::routine(Tree::child($source, ['function_with_argtypes']) ?? throw new UnclassifiedSql('A routine alteration requires its routine.'), $context);
        try {
            return new AlterRoutineStatement($origin, $kind, $target, Collections::nonEmpty(RoutineOptions::read($origin, $source, $context)));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::RoutineAttribute, $source, $error);
        }
    }
}
