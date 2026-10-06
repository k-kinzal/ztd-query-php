<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Table;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\AlterPolicy;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\CreatePolicy;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\PolicyCommand;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers row security policies.
 *
 * Rule: PG-POLICY-LOWER-001. Scope: `CreatePolicyStmt`, `AlterPolicyStmt`,
 * `RowSecurityOptionalExpr`, `RowSecurityOptionalWithCheck`,
 * `RowSecurityDefaultToRole`, `RowSecurityOptionalToRole`,
 * `RowSecurityDefaultPermissive`, `RowSecurityDefaultForCmd`,
 * `row_security_cmd`. Termination: no recursion of its own. Source:
 * https://www.postgresql.org/docs/17/sql-createpolicy.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class PolicyRule
{
    /**
     * The command each `row_security_cmd` production names.
     */
    private const COMMANDS = [
        'row_security_cmd: ALL' => PolicyCommand::All, 'row_security_cmd: SELECT' => PolicyCommand::Select,
        'row_security_cmd: INSERT' => PolicyCommand::Insert, 'row_security_cmd: UPDATE' => PolicyCommand::Update,
        'row_security_cmd: DELETE_P' => PolicyCommand::Delete,
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `CreatePolicyStmt`.
     *
     * The word after AS is an identifier that the grammar action of
     * `RowSecurityDefaultPermissive` in `gram.y` compares with `permissive`
     * and `restrictive`; any other word is a syntax error there.
     *
     * @throws ImplementationGap When the production has no rule
     * @throws AnalysisException When the word after AS is neither permissive nor restrictive
     */
    public function create(Node $statement): CreatePolicy
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'CreatePolicyStmt: CREATE POLICY name ON qualified_name RowSecurityDefaultPermissive RowSecurityDefaultForCmd RowSecurityDefaultToRole RowSecurityOptionalExpr RowSecurityOptionalWithCheck') {
            throw ImplementationGap::production($form);
        }
        $mode = $this->lowering->productions->form($form->node(5));
        if ($mode->signature === 'RowSecurityDefaultPermissive: AS IDENT' && !in_array((new Identifiers())->decode($mode->token(1)->text), ['permissive', 'restrictive'], true)) {
            throw new AnalysisException('unrecognized row security option "' . (new Identifiers())->decode($mode->token(1)->text) . '"');
        }
        $command = $this->lowering->productions->form($form->node(6));

        return new CreatePolicy(
            $this->lowering->names->name($form->node(2)),
            $this->lowering->names->qualified($form->node(4)),
            match ($mode->signature) {
                'RowSecurityDefaultPermissive: AS IDENT' => $this->lowering->names->token($mode->token(1)),
                'RowSecurityDefaultPermissive:' => null,
                default => throw ImplementationGap::production($mode),
            },
            match ($command->signature) {
                'RowSecurityDefaultForCmd: FOR row_security_cmd' => $this->command($command->node(1)),
                'RowSecurityDefaultForCmd:' => null,
                default => throw ImplementationGap::production($command),
            },
            $this->roles($form->node(7)),
            $this->expression($form->node(8)),
            $this->expression($form->node(9)),
        );
    }

    /**
     * Lowers `AlterPolicyStmt`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function alter(Node $statement): AlterPolicy
    {
        $form = $this->lowering->productions->form($statement);
        if ($form->signature !== 'AlterPolicyStmt: ALTER POLICY name ON qualified_name RowSecurityOptionalToRole RowSecurityOptionalExpr RowSecurityOptionalWithCheck') {
            throw ImplementationGap::production($form);
        }

        return new AlterPolicy(
            $this->lowering->names->name($form->node(2)),
            $this->lowering->names->qualified($form->node(4)),
            $this->roles($form->node(5)),
            $this->expression($form->node(6)),
            $this->expression($form->node(7)),
        );
    }

    /**
     * Lowers `row_security_cmd`.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function command(Node $command): PolicyCommand
    {
        $form = $this->lowering->productions->form($command);

        return self::COMMANDS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers `RowSecurityDefaultToRole` or `RowSecurityOptionalToRole`.
     *
     * @return list<RoleSpec>
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function roles(Node $clause): array
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'RowSecurityDefaultToRole: TO role_list', 'RowSecurityOptionalToRole: TO role_list' => $this->lowering->roles->roles($form->node(1)),
            'RowSecurityDefaultToRole:', 'RowSecurityOptionalToRole:' => [],
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers `RowSecurityOptionalExpr` or `RowSecurityOptionalWithCheck`; none is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function expression(Node $clause): ?Scalar
    {
        $form = $this->lowering->productions->form($clause);

        return match ($form->signature) {
            'RowSecurityOptionalExpr: USING ( a_expr )' => $this->lowering->expressions->expression($form->node(2)),
            'RowSecurityOptionalWithCheck: WITH CHECK ( a_expr )' => $this->lowering->expressions->expression($form->node(3)),
            'RowSecurityOptionalExpr:', 'RowSecurityOptionalWithCheck:' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
