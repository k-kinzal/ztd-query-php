<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineAlteration;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the four declared routine-characteristic roles and normalizes repeated assignments.
 * @visibility SqlSemantics
 */
final class CharacteristicBinder
{
    /**
     * Follows the grammar's last-declaration rule without calling the routine.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function bind(Node $source, Identifiers $identifiers): RoutineAlteration
    {
        $language = null;
        $access = null;
        $security = null;
        $comment = null;
        foreach (Tree::outer($source, ['sp_chistic']) as $characteristic) {
            $tokens = $characteristic->tokens();
            $kind = strtoupper($tokens[0]->text);
            if ($kind === 'COMMENT') {
                $comment = (new LiteralBinder(Dialect::MySql))->bind($tokens[1]);
                if (!$comment instanceof Literal) {
                    throw new UnclassifiedSql('A routine comment requires a text literal.');
                }
            } elseif ($kind === 'LANGUAGE') {
                $language = $identifiers->name($tokens[1]);
                if ($language === '') {
                    throw new InvalidSql(InputViolation::RoutineLanguage, $characteristic);
                }
                $language = strcasecmp($language, 'SQL') === 0 ? 'SQL' : $language;
            } elseif ($kind === 'SQL') {
                $security = RoutineSecurity::from(strtoupper($tokens[2]->text));
            } else {
                $access = SqlDataAccess::tryFrom(strtoupper(Tree::text($characteristic))) ?? throw new UnclassifiedSql('Unclassified routine characteristic: ' . Tree::text($characteristic));
            }
        }
        return new RoutineAlteration($language, $access, $security, $comment);
    }
}
