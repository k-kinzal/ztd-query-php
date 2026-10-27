<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use Override;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Source\Span;

/**
 * `GRANT role, … TO account, … [WITH ADMIN OPTION]` (8.0+): a request to grant roles.
 *
 * The nonempty role list accepts only GrantedRole operands. Static and
 * column privileges are refused by input lowering before publication. Anonymous
 * account operands and explicit self-grants remain in the request with a typed
 * intrinsic condition. CURRENT_USER remains a session-dependent reference.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-roles. Status: Implemented.
 *
 * @visibility public
 * @example Granting roles with the admin option
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("grant r1, 'r2'@'%' to u with admin option")->toString() // => 'GRANT r1, r2@`%` TO u WITH ADMIN OPTION'
 */
final class GrantRoles extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, PrivilegeChange, AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<GrantedRole> The granted roles in order
     */
    public readonly array $roles;

    /**
     * @var non-empty-list<Account> The accounts the roles are granted to
     */
    public readonly array $users;

    /**
     * The intrinsic account contradiction, independently of installed role edges.
     */
    public readonly ?\SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles $problem;

    /**
     * @param list<GrantedRole> $roles The granted roles in order; at least one
     * @param list<Account> $users The accounts the roles are granted to; at least one
     * @param bool $withAdminOption Whether WITH ADMIN OPTION is written
     * @param LanguageProfile $profile The selected MySQL language
     * @param Charset|null $client The client encoding used to reconstruct names
     * @param Span|null $source The optional original statement location
     */
    public function __construct(array $roles, array $users, public readonly bool $withAdminOption = false, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($profile->grammar->database() === 'mysql', 'A role grant uses a MySQL language profile.');
        $this->roles = Check::listOf($roles, GrantedRole::class, 'GRANT names at least one role.', 1);
        $this->users = Check::listOf($users, Account::class, 'GRANT names at least one account.', 1);
        $this->problem = (new \SqlSemantics\Platform\MySql\Rules\Account\Privilege\RoleGrants())->contradictory($this->roles, $this->users) ? new \SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles($this->roles, $this->users) : null;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: role operands have no relation or expression bindings.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT')->list($this->roles)->keyword('TO')->list($this->users);
        if ($this->withAdminOption) {
            $out->keyword('WITH', 'ADMIN', 'OPTION');
        }
    }

    /**
     * Reconstructs the complete request from its operands and selected language.
     */
    #[Override]
    public function toString(): string
    {
        $out = new Output(new \SqlSemantics\Platform\MySql\Rendering\Codec($this->profile->grammar, $this->profile->lexical, $this->client));
        $this->render($out);
        return (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()) . $out->trailing();
    }

    /**
     * Answers the selected language without retaining the analysis environment.
     */
    #[Override]
    public function profile(): LanguageProfile
    {
        return $this->profile;
    }

    /**
     * Role grants contribute no table declarations.
     *
     * @return list<\SqlSemantics\Statement\Declaration\Table>
     */
    #[Override]
    public function declarations(): array
    {
        return [];
    }
}
