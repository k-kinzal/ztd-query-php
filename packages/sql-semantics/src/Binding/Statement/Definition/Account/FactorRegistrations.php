<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationFactor;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorIdentification;
use SqlSemantics\Model\Definition\Account\Alteration\FactorOperation;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Statement\Definition\MySql\Account\FinishRegistrationStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\InitiateRegistrationStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Account\UnregisterFactorStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds numbered-factor operations: ADD, MODIFY, DROP, and device registration.
 * @visibility SqlSemantics
 */
final class FactorRegistrations
{
    /**
     * Registration forms address one factor of one account and never carry shared clauses.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Origin $origin, Node $registration, AccountName|CurrentAccount|ClientAccount $account): BoundStatement
    {
        $factor = self::factor(Tree::child($registration, ['factor']) ?? throw new UnclassifiedSql('Factor registration requires its factor.'));
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $registration->tokens());
        $tokens = $registration->tokens();
        return match ($words[2] ?? '') {
            'INITIATE' => new InitiateRegistrationStatement($origin, $account, $factor),
            'UNREGISTER' => new UnregisterFactorStatement($origin, $account, $factor),
            'FINISH' => new FinishRegistrationStatement($origin, $account, $factor, Accounts::literal($tokens[count($tokens) - 1])),
            default => throw new UnclassifiedSql('Unclassified factor registration: ' . Tree::text($registration)),
        };
    }

    /**
     * ADD and MODIFY pair each factor with its identification; DROP lists factors only.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function change(Node $change, AccountName|CurrentAccount $account, string $operation, Identifiers $identifiers): FactorChange|FactorRemoval
    {
        $factors = array_map(self::factor(...), Tree::outer($change, ['factor']));
        if ($factors === []) {
            throw new UnclassifiedSql('A factor change requires at least one factor.');
        }
        try {
            if ($operation === 'DROP') {
                return new FactorRemoval($account, Collections::nonEmpty($factors));
            }
            $identifications = Tree::outer($change, ['identification']);
            if (count($identifications) !== count($factors)) {
                throw new UnclassifiedSql('Each added or modified factor requires one identification.');
            }
            $pairs = [];
            foreach ($factors as $index => $factor) {
                $pairs[] = new FactorIdentification($factor, Identifications::read($identifications[$index], $identifiers));
            }
            return new FactorChange($account, FactorOperation::from($operation), Collections::nonEmpty($pairs));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::AuthenticationFactor, $change, $error);
        }
    }

    /**
     * Only the second and third factors are numbered; any other number is an impossible factor.
     * @throws InvalidSql
     */
    public static function factor(Node $factor): AuthenticationFactor
    {
        $text = ltrim($factor->tokens()[0]->text, '0');
        return AuthenticationFactor::tryFrom($text) ?? throw new InvalidSql(InputViolation::AuthenticationFactor, $factor);
    }
}
