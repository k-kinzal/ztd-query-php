<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Configuration\Password;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Configuration\SettingTokens;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Statement\Configuration\Password as Statement;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Preserves the ordered credential and assignment effects allowed by the MySQL 5.6 SET grammar.
 * @visibility SqlSemantics
 */
final class LegacyPasswordList
{
    /**
     * @param non-empty-list<Node> $passwords
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $source, array $passwords, Scope $scope): Statement\SetPasswordHashStatement|Statement\SetDerivedPasswordStatement|Statement\SetAccountOptionsStatement
    {
        $operations = [];
        $groups = SettingTokens::split(array_slice($source->tokens(), 1));
        $inheritedScope = null;
        foreach ($groups as $group) {
            if (($group[0]->name ?? '') === 'PASSWORD') {
                $node = array_shift($passwords);
                if ($node === null) {
                    throw new UnclassifiedSql('A mixed SET credential requires its password production.');
                }
                $operation = PasswordBinder::operation($origin, $node, $scope);
                if (!$operation instanceof Statement\SetPasswordHashStatement && !$operation instanceof Statement\SetDerivedPasswordStatement) {
                    throw new UnclassifiedSql('Mixed password clauses require MySQL 5.6 credential forms.');
                }
                $operations[] = $operation;
            } else {
                $first = $group[0] ?? null;
                if ($first !== null && in_array($first->name, ['GLOBAL_SYM', 'SESSION_SYM', 'LOCAL_SYM'], true) && ($group[1]->text ?? '') !== '.') {
                    $inheritedScope = $first;
                } elseif ($inheritedScope !== null && ($first->text ?? '') !== '@') {
                    array_unshift($group, $inheritedScope);
                }
                array_push($operations, ...LegacyAssignments::bind($origin, $group, $source, $scope));
            }
        }
        if (count($operations) === 1 && !$operations[0] instanceof SetStatement) {
            return $operations[0];
        }
        return new Statement\SetAccountOptionsStatement($origin, \SqlSemantics\Model\Validation\Collections::nonEmpty($operations));
    }


}
