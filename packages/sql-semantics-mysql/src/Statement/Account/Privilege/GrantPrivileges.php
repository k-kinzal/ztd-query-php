<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `GRANT privileges ON [kind] level TO accounts [REQUIRE …] [WITH …] [AS user [WITH ROLE …]]`: a request to grant privileges.
 *
 * Mirrors the SQLCOM_GRANT path of LEX (grant, columns, type, users_list,
 * grant_as). Rule: MYSQL-GRANT-001. The level and its table are derived by
 * MYSQL-PRIVILEGE-CHECKS-001: a table level records the resolution of the
 * table as the relation fact of the level. MySQL 5.x also writes an
 * IDENTIFIED clause per account, a REQUIRE clause and resource limits in the
 * WITH clause, which set the account as CREATE USER and ALTER USER do; their
 * problems are reported by MYSQL-ACCOUNT-CHECKS-001. The statement provides
 * no declaration. ON TABLE and ON are the same request; ALL and ALL
 * PRIVILEGES are the same request.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html,
 * https://dev.mysql.com/doc/refman/5.7/en/grant.html. Status: Implemented.
 *
 * Intrinsic level conditions remain beside the complete request. Stored programs
 * bind and execute the same statement type when the instruction is reached.
 *
 * @visibility public
 * @example Refusing column privileges on a routine
 *     new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [new \SqlSemantics\Statement\Identifier\Name('a')])], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p'))), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Granting table privileges with the grant option
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('grant select, insert on table shop.t to app@localhost with grant option')->toString() // => 'GRANT SELECT, INSERT ON TABLE shop.t TO app@localhost WITH GRANT OPTION'
 */
final class GrantPrivileges extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement, PrivilegeChange, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Privilege> The granted privileges in order; ALL stands alone
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<Grantee> The accounts privileges are granted to
     */
    public readonly array $grantees;

    /**
     * @var list<WithOption> The options of the WITH clause in order
     */
    public readonly array $options;

    /**
     * @param list<Privilege> $privileges The granted privileges in order; ALL stands alone
     * @param ObjectKind $kind The kind of object the level names
     * @param PrivilegeLevel $level The privilege level
     * @param list<Grantee|UserSpecification> $grantees The accounts privileges are granted to; at least one
     * @param TlsRequirement|null $tls The REQUIRE clause of MySQL 5.x, when written
     * @param list<WithOption> $options The options of the WITH clause in order
     * @param GrantAs|null $as The AS clause, when written
     * @param bool $tableKeyword Whether TABLE is explicitly written for a table grant
     */
    public function __construct(
        array $privileges,
        public readonly ObjectKind $kind,
        public readonly PrivilegeLevel $level,
        array $grantees,
        public readonly ?TlsRequirement $tls = null,
        array $options = [],
        public readonly ?GrantAs $as = null,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly bool $tableKeyword = false
    ) {
        Check::input(!$tableKeyword || $kind === ObjectKind::Table, 'TABLE names a table-level privilege object.');
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->privileges = Check::listOf($privileges, Privilege::class, 'GRANT names at least one privilege.', 1);
        $this->grantees = array_map(Grantee::from(...), Check::listOf($grantees, \SqlSemantics\Statement\Node::class, 'GRANT names at least one account.', 1));
        $this->options = Check::listOf($options, WithOption::class, 'The WITH clause lists options.');
        foreach ($this->privileges as $privilege) {
            Check::input($kind === ObjectKind::Table || !$privilege instanceof Item\StaticPrivilege || $privilege->columns === [], 'Column privileges apply only to tables.');
            Check::input(!$privilege instanceof Item\AllPrivileges || count($this->privileges) === 1, 'ALL is the only item of its list.');
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers a target-kind, column-level, dynamic-level or static object privilege contradiction.
     *
     * Database-only static restrictions have later account resolution precedence
     * and are derived separately. Dynamic privileges precede object lookup.
     */
    public function scopeProblem(): ?\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege
    {
        if ($this->kind !== ObjectKind::Table && !$this->level instanceof Level\ObjectLevel) {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege((string) $this->kind->keyword(), $this->level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE');
        }
        $invalid = null;
        $dynamic = null;
        foreach ($this->privileges as $privilege) {
            if ($privilege instanceof Item\DynamicPrivilege && !$this->level instanceof Level\GlobalLevel) {
                $dynamic ??= new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege($privilege->name->value, $this->level->describe(), 'ER_ILLEGAL_PRIVILEGE_LEVEL');
            }
            if (!$privilege instanceof Item\StaticPrivilege) {
                continue;
            }
            if ($privilege->columns !== [] && !$this->level instanceof Level\ObjectLevel) {
                return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege($privilege->kind->value . ' with a column list', $this->level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE');
            }
            if ($this->level instanceof Level\ObjectLevel && !($this->kind === ObjectKind::Table ? $privilege->kind->table() : $privilege->kind->routine())) {
                $invalid ??= new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege($privilege->kind->value, $this->level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE');
            }
        }
        return $dynamic ?? $invalid;
    }

    /**
     * Identifies column privileges outside a table target, refused at legacy definition time.
     */
    public function columnLevel(): bool
    {
        return !$this->level instanceof Level\ObjectLevel && array_filter($this->privileges, static fn (Privilege $privilege): bool => $privilege instanceof Item\StaticPrivilege && $privilege->columns !== []) !== [];
    }

    /**
     * Answers the condition of a complete privilege request before object lookup.
     */
    public function condition(): ?\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege
    {
        if ($this->as !== null && !$this->level instanceof Level\GlobalLevel) {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege('AS clause', $this->level->describe(), 'ER_UNSUPPORTED_USE_OF_GRANT_AS');
        }
        return $this->scopeProblem();
    }

    /**
     * Answers the condition the server reports once a standalone request is read: the AS clause outside the
     * global level, then the privilege, column list or object kind that does not fit the level.
     */
    public function problem(): ?\SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege
    {
        return $this->condition() ?? (new PrivilegeChecks())->problem($this->privileges, $this->kind, $this->level, false);
    }

    /**
     * Derives the complete request, keeping invocation conditions out of stored definitions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new PrivilegeChecks())->notices($this->privileges, $derivation);
        $legacy = in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $problem = $legacy ? (new PrivilegeChecks())->columnLevel($this->privileges, $this->level) : null;
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $this->level);
            return;
        }
        (new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames())->derive($derivation, [...$this->grantees, $this->as]);
        $condition = $this->condition();
        if ($condition !== null && !$derivation->inProgram()) {
            $derivation->report($condition);
        }
        if ($this->scopeProblem() === null) {
            (new PrivilegeChecks())->grant($derivation, $this->privileges, $this->kind, $this->level);
        }
    }

    /**
     * Derives a stored request through the same statement boundary as ordinary GRANT.
     */
    public function deriveProgram(Derivation $derivation, \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope $scope): void
    {
        if ($scope->kind !== null) {
            (new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramRestrictions())->check($this, $scope->kind, $derivation);
        }
        $derivation->program($this, $scope->environment);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT')->list($this->privileges)->keyword('ON');
        $keyword = $this->tableKeyword ? 'TABLE' : $this->kind->keyword();
        if ($keyword !== null) {
            $out->keyword($keyword);
        }
        $out->node($this->level)->keyword('TO')->list($this->grantees)->node($this->tls);
        if ($this->options !== []) {
            $out->keyword('WITH');
            foreach ($this->options as $option) {
                $out->node($option);
            }
        }
        $out->node($this->as);
    }
}
