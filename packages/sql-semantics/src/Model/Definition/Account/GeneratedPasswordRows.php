<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\ClientAccount;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Configuration\Account\GeneratedPasswordColumn;
use SqlSemantics\Model\Configuration\Account\GeneratedPasswordField;
use SqlSemantics\Model\Definition\Account\Alteration\AccountTarget;
use SqlSemantics\Model\Definition\Account\Alteration\AuthenticationChange;
use SqlSemantics\Model\Definition\Account\Alteration\CredentialChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorChange;
use SqlSemantics\Model\Definition\Account\Alteration\FactorRemoval;
use SqlSemantics\Model\Definition\Account\Alteration\OldPasswordDiscard;
use SqlSemantics\Model\Definition\Account\Alteration\PluginChange;
use SqlSemantics\Model\Definition\Account\Identification\PluginRandomPasswordIdentification;
use SqlSemantics\Model\Definition\Account\Identification\RandomPassword;
use SqlSemantics\Model\OutputColumn;

/**
 * Derives the generated-password result rows of CREATE USER and ALTER USER from their random-password requests.
 * @visibility SqlSemantics
 */
final class GeneratedPasswordRows
{
    /**
     * Whether a definition asks the server to generate a password for any factor.
     */
    public static function definition(AccountDefinition|InitialAuthenticationDefinition $account): bool
    {
        if ($account instanceof InitialAuthenticationDefinition) {
            return $account->initialAuthentication === RandomPassword::Generated;
        }
        foreach ([$account->identification, ...$account->additionalFactors] as $identification) {
            if ($identification instanceof RandomPassword || $identification instanceof PluginRandomPasswordIdentification) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether an alteration asks the server to generate a password for any factor.
     */
    public static function alteration(CredentialChange|AuthenticationChange|PluginChange|AccountTarget|OldPasswordDiscard|FactorChange|FactorRemoval $alteration): bool
    {
        if ($alteration instanceof CredentialChange || $alteration instanceof AuthenticationChange) {
            return $alteration->identification instanceof RandomPassword || $alteration->identification instanceof PluginRandomPasswordIdentification;
        }
        if (!$alteration instanceof FactorChange) {
            return false;
        }
        foreach ($alteration->factors as $factor) {
            if ($factor->identification instanceof RandomPassword || $factor->identification instanceof PluginRandomPasswordIdentification) {
                return true;
            }
        }
        return false;
    }

    /**
     * The user, host, generated password, and factor fields; empty when no password is generated.
     * @return list<OutputColumn>
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function columns(Node|Token $source, string $scopeId, AccountName|CurrentAccount|ClientAccount|null $account): array
    {
        if ($account === null) {
            return [];
        }
        $columns = [];
        foreach (GeneratedPasswordField::cases() as $ordinal => $field) {
            $columns[] = new OutputColumn($ordinal, $field->value, new GeneratedPasswordColumn($source, $scopeId, $account, $field));
        }
        return $columns;
    }
}
