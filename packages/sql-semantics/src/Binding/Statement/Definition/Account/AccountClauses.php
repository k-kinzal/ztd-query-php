<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Account;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Account\Policy\AccountAnnotation;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimit;
use SqlSemantics\Model\Definition\Account\Policy\AccountLimitKind;
use SqlSemantics\Model\Definition\Account\Policy\AccountPolicy;
use SqlSemantics\Model\Definition\Account\Policy\AnnotationForm;
use SqlSemantics\Model\Definition\Account\Policy\CertificateAttribute;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirement;
use SqlSemantics\Model\Definition\Account\Policy\CertificateRequirements;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimit;
use SqlSemantics\Model\Definition\Account\Policy\ResourceLimitKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads the REQUIRE, WITH, lock, password, and metadata clauses shared by account and legacy grant statements.
 * @visibility SqlSemantics
 */
final class AccountClauses
{
    /**
     * REQUIRE NONE, SSL, or X509 is a transport requirement; a list is a set of certificate constraints.
     * @throws InvalidSql
     */
    public static function requirement(Node $statement): ConnectionSecurity|CertificateRequirements|null
    {
        $clause = Tree::child($statement, ['require_clause']);
        if ($clause === null) {
            return null;
        }
        $security = ConnectionSecurity::tryFrom(strtoupper($clause->tokens()[1]->text ?? ''));
        if ($security !== null) {
            return $security;
        }
        $requirements = [];
        foreach (Tree::outer($clause, ['require_list_element']) as $element) {
            $tokens = $element->tokens();
            $requirements[] = new CertificateRequirement(CertificateAttribute::from(strtoupper($tokens[0]->text)), Accounts::literal($tokens[1]));
        }
        try {
            return new CertificateRequirements(Collections::nonEmpty($requirements));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::CertificateRequirement, $clause, $error);
        }
    }

    /**
     * Reads MAX_* limits from connect_option or legacy grant_option nodes in request order.
     * @return list<ResourceLimit>
     * @throws InvalidSql
     */
    public static function limits(Node $statement): array
    {
        $limits = [];
        foreach (Tree::outer($statement, ['connect_option', 'grant_option']) as $option) {
            $kind = ResourceLimitKind::tryFrom(strtoupper($option->tokens()[0]->text));
            if ($kind === null) {
                continue;
            }
            try {
                $limits[] = new ResourceLimit($kind, Accounts::count($option->tokens()[1], $option));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::AccountLimit, $option, $error);
            }
        }
        return $limits;
    }

    /**
     * Keyword-only policies become enum cases; policies with a count become typed limits.
     * @return list<AccountPolicy|AccountLimit>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function policies(Node $statement): array
    {
        $policies = [];
        foreach (Tree::outer($statement, ['opt_account_lock_password_expire_option']) as $option) {
            $number = Tree::child($option, ['real_ulong_num']);
            $words = [];
            foreach (Tree::significant($option) as $child) {
                if ($child === $number) {
                    break;
                }
                $words[] = strtoupper(Tree::text($child));
            }
            $text = implode(' ', $words);
            if ($number === null) {
                $policies[] = AccountPolicy::tryFrom($text) ?? throw new UnclassifiedSql('Unclassified account policy: ' . $text);
                continue;
            }
            try {
                $policies[] = new AccountLimit(AccountLimitKind::tryFrom($text) ?? throw new UnclassifiedSql('Unclassified account limit: ' . $text), Accounts::count($number->tokens()[0], $option));
            } catch (InvalidStructure $error) {
                throw new InvalidSql(InputViolation::AccountLimit, $option, $error);
            }
        }
        return $policies;
    }

    /**
     * COMMENT and ATTRIBUTE keep their text literal without parsing it.
     * @throws UnclassifiedSql
     */
    public static function annotation(Node $statement): ?AccountAnnotation
    {
        $clause = Tree::child($statement, ['opt_user_attribute']);
        if ($clause === null) {
            return null;
        }
        $tokens = $clause->tokens();
        return new AccountAnnotation(AnnotationForm::from(strtoupper($tokens[0]->text)), Accounts::literal($tokens[count($tokens) - 1]));
    }

    /**
     * Whether a clause list contains the GRANT OPTION keywords outside a privilege list.
     */
    public static function grantOption(Node $statement, string $rule): bool
    {
        $clause = Tree::child($statement, [$rule]);
        if ($clause === null) {
            return false;
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $clause->tokens());
        foreach ($words as $index => $word) {
            if ($word === 'GRANT' && ($words[$index + 1] ?? '') === 'OPTION') {
                return true;
            }
        }
        return false;
    }
}
