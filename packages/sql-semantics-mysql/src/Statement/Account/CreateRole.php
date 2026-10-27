<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account;

use Override;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Source\Span;

/**
 * `CREATE ROLE [IF NOT EXISTS] role, …` (8.0+): a request to create roles, which are locked accounts.
 *
 * The complete ordered request retains anonymous and repeated identities.
 * Its intrinsic condition is distinct from catalog-dependent execution: with
 * IF NOT EXISTS an existing anonymous or repeated account can be skipped.
 * Mirrors PT_create_role. Rule: MYSQL-CREATE-ROLE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-role.html. Status: Implemented.
 *
 * @visibility public
 * @example Creating roles
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create role if not exists reader, 'writer'@'%'")->toString() // => 'CREATE ROLE IF NOT EXISTS reader, writer@`%`'
 */
final class CreateRole extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<AccountName> The roles in order
     */
    public readonly array $roles;

    /**
     * The intrinsic account contradiction, independently of installed accounts.
     */
    public readonly ?Problem\Role\RefusedCreateRole $problem;

    /**
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param list<AccountName> $roles The roles in order; at least one
     * @param LanguageProfile $profile The selected MySQL language
     * @param Charset|null $client The client encoding used to reconstruct names
     * @param Span|null $source The optional original statement location
     */
    public function __construct(public readonly bool $ifNotExists, array $roles, public readonly LanguageProfile $profile = new LanguageProfile(GrammarRelease::MySql847), public readonly ?Charset $client = null, public readonly ?Span $source = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($profile->grammar->database() === 'mysql', 'A role definition uses a MySQL language profile.');
        $this->roles = Check::listOf($roles, AccountName::class, 'CREATE ROLE names at least one role.', 1);
        $this->problem = (new \SqlSemantics\Platform\MySql\Rules\Account\RoleDefinitions())->problem($this->ifNotExists, $this->roles) === null ? null : new Problem\Role\RefusedCreateRole($this->ifNotExists, $this->roles);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'ROLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->list($this->roles);
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
     * Account definitions contribute no table declarations.
     *
     * @return list<\SqlSemantics\Statement\Declaration\Table>
     */
    #[Override]
    public function declarations(): array
    {
        return [];
    }
}
