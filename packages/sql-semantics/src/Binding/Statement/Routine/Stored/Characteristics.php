<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Stored;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads the characteristics of a new routine; the last declaration of each property applies.
 * @visibility SqlSemantics
 */
final class Characteristics
{
    /**
     * Returns the characteristics and the declared language, SQL when omitted.
     * @return array{RoutineCharacteristics, string}
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function read(?Node $list, QueryContext $context): array
    {
        $deterministic = false;
        $access = SqlDataAccess::Contains;
        $security = RoutineSecurity::Definer;
        $comment = null;
        $language = 'SQL';
        foreach ($list === null ? [] : Tree::outer($list, ['sp_c_chistic']) as $characteristic) {
            $tokens = $characteristic->tokens();
            $words = strtoupper(implode(' ', array_map(static fn ($token): string => $token->text, $tokens)));
            $first = strtoupper($tokens[0]->text ?? '');
            if (str_ends_with($words, 'DETERMINISTIC')) {
                $deterministic = count($tokens) === 1;
            } elseif ($first === 'COMMENT') {
                $comment = (new LiteralBinder(Dialect::MySql))->bind($tokens[1]);
                $comment = $comment instanceof Literal ? $comment : throw new UnclassifiedSql('A routine comment requires a text literal.');
            } elseif ($first === 'LANGUAGE') {
                $language = $context->tables->identifiers->name($tokens[1]);
                $language = $language === '' ? throw new InvalidSql(InputViolation::RoutineLanguage, $characteristic) : (strcasecmp($language, 'SQL') === 0 ? 'SQL' : $language);
            } elseif ($first === 'SQL') {
                $security = RoutineSecurity::from(strtoupper($tokens[2]->text));
            } else {
                $access = SqlDataAccess::tryFrom($words) ?? throw new UnclassifiedSql('Unclassified routine characteristic: ' . $words);
            }
        }
        return [new RoutineCharacteristics($deterministic, $access, $security, $comment), $language];
    }
}
