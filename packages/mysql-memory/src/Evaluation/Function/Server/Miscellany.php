<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Server;

use MySqlMemory\Account\Account;
use MySqlMemory\Account\Accounts;
use MySqlMemory\Account\Identity;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\Minus;
use MySqlMemory\Session\Variables;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The miscellaneous functions ANY_VALUE, NAME_CONST, ICU_VERSION and ROLES_GRAPHML.
 *
 * ANY_VALUE(value) answers its argument. NAME_CONST(name, value) answers its value and names its
 * column after the name; both are literals, the value possibly negated or given a collation, or
 * the call is ER_WRONG_ARGUMENTS, and a NULL name is ER_RESERVED_SYNTAX. ICU_VERSION() is the
 * version of the ICU library the release bundles. ROLES_GRAPHML() is a GraphML document of the
 * accounts of the server, one node each, and of the roles granted, one edge from each grantee to
 * its role, colored 1 when ADMIN OPTION is held; the system accounts come first in the order of a
 * freshly started server, then the accounts in the order they were created, and the edges of
 * each grantee in the order its first role was granted. A session without ROLE_ADMIN or SUPER
 * gets an empty graph (verified on live 8.0.44, 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_icu-version,
 * https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_roles-graphml.
 *
 * @visibility MySqlMemory
 */
final class Miscellany
{
    /**
     * The system accounts of a new installation, in the order the role graph of a freshly started server holds them.
     */
    public const SYSTEM = ["mysql.infoschema\0localhost", "mysql.session\0localhost", "mysql.sys\0localhost", "root\0localhost", "root\0%"];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('ANY_VALUE', 1, 1, fn (Frame $f, array $a): int|float|string|null => $a[0]->evaluate($f)),
            new Routine('NAME_CONST', 2, 2, fn (Frame $f, array $a): int|float|string|null => $a[1]->evaluate($f), resolve: $this->constants(...)),
            new Routine('ICU_VERSION', 0, 0, fn (Frame $f): string => $this->icu($f->context->modes->release)),
            new Routine('ROLES_GRAPHML', 0, 0, fn (Frame $f): string => $this->graph($f->context->variables)),
        ];
    }

    /**
     * Checks the arguments of NAME_CONST() when the call is compiled: a literal name and a literal value.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known
     * @return list<Evaluable>
     *
     * @throws \MySqlMemory\Error\SqlError When an argument is not a literal, or the name is NULL
     */
    public function constants(Frame $frame, array $arguments, array $known): array
    {
        $bare = static function (Evaluable $argument): Evaluable {
            while ($argument instanceof Retyped) {
                $argument = $argument->evaluable;
            }

            return $argument;
        };
        $value = $bare($arguments[1]);
        if ($value instanceof Minus || ($value instanceof Call && $value->routine->name === 'COLLATE')) {
            $value = $bare($value instanceof Minus ? $value->operand : $value->arguments[0]);
        }
        $literal = static fn (Evaluable $argument): bool => $argument instanceof Constant && !$argument->domain->kind->temporal();
        if (!$literal($bare($arguments[0])) || !$literal($value)) {
            throw StatementError::WrongArguments->error('NAME_CONST');
        }
        if ($arguments[0]->evaluate($frame) === null) {
            throw StatementError::ReservedSyntax->error('NAME_CONST');
        }

        return $arguments;
    }

    /**
     * Answers the version of the ICU library a release bundles.
     */
    public function icu(GrammarRelease $release): string
    {
        return match ($release) {
            GrammarRelease::MySql8044, GrammarRelease::MySql847 => '77.1',
            GrammarRelease::MySql810 => '69.1',
            GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql901, GrammarRelease::MySql910,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => '73.1',
        };
    }

    /**
     * Writes the role graph of the server as GraphML, or an empty graph for a session that may not read it.
     */
    public function graph(Variables $variables): string
    {
        $head = '<?xml version="1.0" encoding="UTF-8"?>';
        $accounts = $variables->instance->accounts;
        if (!$this->administers($accounts, $variables)) {
            return $head . '<graphml />';
        }
        $order = array_keys($accounts->accounts);
        usort($order, static fn (string $a, string $b): int => [array_search($a, self::SYSTEM, true) === false ? 1 : 0, (int) array_search($a, self::SYSTEM, true), array_search($a, array_keys($accounts->accounts), true)]
            <=> [array_search($b, self::SYSTEM, true) === false ? 1 : 0, (int) array_search($b, self::SYSTEM, true), array_search($b, array_keys($accounts->accounts), true)]);
        $nodes = array_flip($order);
        $escape = static fn (Identity $identity): string => htmlspecialchars($identity->backquoted(), ENT_QUOTES | ENT_XML1);
        $text = $head . "\n" . '<graphml xmlns="http://graphml.graphdrawing.org/xmlns" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://graphml.graphdrawing.org/xmlns http://graphml.graphdrawing.org/xmlns/1.0/graphml.xsd">' . "\n"
            . '  <key id="key0" for="edge" attr.name="color" attr.type="int" />' . "\n"
            . '  <key id="key1" for="node" attr.name="name" attr.type="string" />' . "\n"
            . '  <graph id="G" edgedefault="directed" parse.nodeids="canonical" parse.edgeids="canonical" parse.order="nodesfirst">' . "\n";
        foreach ($order as $index => $key) {
            $text .= '    <node id="n' . $index . '">' . "\n" . '      <data key="key1">' . $escape($accounts->accounts[$key]->identity) . '</data>' . "\n" . '    </node>' . "\n";
        }
        $edge = 0;
        foreach ($accounts->edges as $grantee => $roles) {
            foreach ($roles as $role => [, $admin]) {
                if (isset($nodes[$grantee], $nodes[$role])) {
                    $text .= '    <edge id="e' . $edge++ . '" source="n' . $nodes[$grantee] . '" target="n' . $nodes[$role] . '">' . "\n" . '      <data key="key0">' . ($admin ? 1 : 0) . '</data>' . "\n" . '    </edge>' . "\n";
                }
            }
        }

        return $text . '  </graph>' . "\n" . '</graphml>' . "\n";
    }

    /**
     * Tells whether the account of a session, or a role active in it, holds ROLE_ADMIN or SUPER; an account the server does not hold is not refused.
     */
    public function administers(Accounts $accounts, Variables $variables): bool
    {
        $at = strrpos($variables->definer, '@');
        $account = $accounts->find($at === false ? Identity::of($variables->definer, '%') : Identity::of(substr($variables->definer, 0, $at), substr($variables->definer, $at + 1)));
        if ($account === null) {
            return true;
        }
        $holders = [$account, ...array_filter(array_map(static fn (Identity $role): ?Account => $accounts->find($role), $variables->roles))];
        foreach ($holders as $holder) {
            if (isset($holder->grants->dynamic['ROLE_ADMIN']) || isset($holder->grants->global->names['SUPER'])) {
                return true;
            }
        }

        return false;
    }
}
