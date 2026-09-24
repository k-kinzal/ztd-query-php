<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Utility\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Binding\Statement\Utility\QualifiedNames;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\ConstraintTiming;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\Model\Statement\Configuration\SetNamedConstraintsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds PostgreSQL SET CONSTRAINTS for every deferrable constraint or for named constraints.
 * @visibility SqlSemantics
 */
final class ConstraintTimings
{
    /**
     * Constraint names are recorded as written; the server looks them up in the search path at execution.
     * @throws InvalidSql
     * @throws InvalidStructure
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, QueryContext $context): SetAllConstraintsStatement|SetNamedConstraintsStatement
    {
        $mode = Tree::child($source, ['constraints_set_mode']) ?? throw new UnclassifiedSql('SET CONSTRAINTS requires its checking mode.');
        $timing = ConstraintTiming::from(strtoupper($mode->tokens()[0]->text ?? ''));
        $names = Tree::outer($source, ['qualified_name']);
        if ($names === []) {
            return new SetAllConstraintsStatement($origin, $timing);
        }
        return new SetNamedConstraintsStatement($origin, array_map(static fn (Node $name) => QualifiedNames::read($name, $context->tables->identifiers), $names), $timing);
    }
}
