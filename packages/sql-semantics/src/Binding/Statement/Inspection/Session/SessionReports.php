<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Configuration\Role\AccountNames;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Inspection\Filters;
use SqlSemantics\Binding\Statement\Inspection\ShowRequest;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;
use SqlSemantics\Model\Statement\Inspection\Session;
use SqlSemantics\Model\Statement\Origin;

/**
 * Classifies SHOW requests about variables, status counters, diagnostics, and grants.
 * @visibility SqlSemantics
 */
final class SessionReports
{
    /**
     * Routes by the keyword following SHOW; a leading scope keyword applies to variables and status only.
     */
    public static function bind(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        return match ($request->word(0)) {
            'WARNINGS', 'ERRORS' => new Session\ShowDiagnosticsStatement($origin, DiagnosticSelection::from($request->word(0)), RowWindows::read($request->form, $context)),
            'COUNT' => new Session\ShowDiagnosticCountStatement($origin, DiagnosticSelection::from($request->words[count($request->words) - 1])),
            'GRANTS' => self::grants($origin, $request->form, $context),
            default => self::variables($origin, $request, $context),
        };
    }

    /**
     * LOCAL and an omitted scope select the session values; null when the request lists neither variables nor status counters.
     */
    public static function variables(Origin $origin, ShowRequest $request, QueryContext $context): ?BoundStatement
    {
        $scoped = in_array($request->word(0), ['GLOBAL', 'SESSION', 'LOCAL'], true);
        $scope = $request->word(0) === 'GLOBAL' ? VariableScope::Global : VariableScope::Session;
        return match ($request->word($scoped ? 1 : 0)) {
            'VARIABLES' => Filters::restrict(static fn (PatternFilter|ConditionFilter|null $filter): Session\ShowVariablesStatement => new Session\ShowVariablesStatement($origin, $scope, $filter), $request->form, $context),
            'STATUS' => Filters::restrict(static fn (PatternFilter|ConditionFilter|null $filter): Session\ShowStatusStatement => new Session\ShowStatusStatement($origin, $scope, $filter), $request->form, $context),
            default => null,
        };
    }

    /**
     * The described account is the first account node; a USING list names the roles shown as active.
     */
    public static function grants(Origin $origin, Node $form, QueryContext $context): Session\ShowGrantsStatement
    {
        $account = Tree::child($form, ['user']);
        if ($account === null) {
            return new Session\ShowGrantsStatement($origin);
        }
        $roles = Tree::child($form, ['user_list']);
        return new Session\ShowGrantsStatement($origin, self::account($account, $context), $roles === null ? [] : array_map(static fn (Node $role): AccountName|CurrentAccount => self::account($role, $context), Tree::outer($roles, ['user'])));
    }

    /**
     * Reads one account name; CURRENT_USER with or without parentheses names the authenticated account.
     */
    public static function account(Node $user, QueryContext $context): AccountName|CurrentAccount
    {
        $tokens = $user->tokens();
        if ($tokens[0]->name === 'CURRENT_USER') {
            return CurrentAccount::Authenticated;
        }
        $identifiers = $context->tables->identifiers;
        return new AccountName(AccountNames::part($tokens[0], $identifiers), isset($tokens[2]) ? AccountNames::part($tokens[2], $identifiers) : null);
    }
}
