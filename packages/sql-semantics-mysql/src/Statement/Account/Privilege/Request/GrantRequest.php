<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;

/**
 * The privilege, target and account operands shared by immediate and stored GRANT.
 *
 * A request is not a standalone statement. Its scope may instead describe a
 * stored instruction whose condition is raised only when control reaches it.
 * The statement and program-member constructors enforce those distinct roles.
 *
 * @visibility public
 * @example Keeping operands whose level is checked at stored invocation
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');
 *     [$create->body->request instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Request\GrantRequest, $create->body->request->scopeProblem()->error] // => [true, 'ER_ILLEGAL_GRANT_FOR_TABLE']
 */
final class GrantRequest implements Node
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
     */
    public function __construct(
        array $privileges,
        public readonly ObjectKind $kind,
        public readonly PrivilegeLevel $level,
        array $grantees,
        public readonly ?TlsRequirement $tls = null,
        array $options = [],
        public readonly ?GrantAs $as = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        $this->privileges = Check::listOf($privileges, Privilege::class, 'GRANT names at least one privilege.', 1);
        $this->grantees = array_map(Grantee::from(...), Check::listOf($grantees, Node::class, 'GRANT names at least one account.', 1));
        $this->options = Check::listOf($options, WithOption::class, 'The WITH clause lists options.');
        foreach ($this->privileges as $privilege) {
            Check::input($kind === ObjectKind::Table || !$privilege instanceof Item\StaticPrivilege || $privilege->columns === [], 'Column privileges apply only to tables.');
            Check::input(!$privilege instanceof AllPrivileges || count($this->privileges) === 1, 'ALL is the only item of its list.');
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers a target-kind, column-level, dynamic-level or static object privilege contradiction.
     *
     * Database-only static restrictions have later account resolution precedence
     * and are derived separately. Dynamic privileges precede object lookup.
     */
    public function scopeProblem(): ?MisplacedPrivilege
    {
        if ($this->kind !== ObjectKind::Table && !$this->level instanceof Level\ObjectLevel) {
            return new MisplacedPrivilege((string) $this->kind->keyword(), $this->level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE');
        }
        $invalid = null;
        $dynamic = null;
        foreach ($this->privileges as $privilege) {
            if ($privilege instanceof Item\DynamicPrivilege && !$this->level instanceof Level\GlobalLevel) {
                $dynamic ??= new MisplacedPrivilege($privilege->name->value, $this->level->describe(), 'ER_ILLEGAL_PRIVILEGE_LEVEL');
            }
            if (!$privilege instanceof Item\StaticPrivilege) {
                continue;
            }
            if ($privilege->columns !== [] && !$this->level instanceof Level\ObjectLevel) {
                return new MisplacedPrivilege($privilege->kind->value . ' with a column list', $this->level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE');
            }
            if ($this->level instanceof Level\ObjectLevel && !($this->kind === ObjectKind::Table ? $privilege->kind->table() : $privilege->kind->routine())) {
                $invalid ??= new MisplacedPrivilege($privilege->kind->value, $this->level->describe(), 'ER_ILLEGAL_GRANT_FOR_TABLE');
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
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT')->list($this->privileges)->keyword('ON');
        $keyword = $this->kind->keyword();
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
