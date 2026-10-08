<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Assigner;
use MySqlMemory\Variable\Scope;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteral;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * The hints of a statement applied to the session while the statement runs: RESOURCE_GROUP, the SET_VAR values, and the warnings to raise once the statement starts.
 *
 * The server reads the hints, then binds the thread to the resource group of RESOURCE_GROUP
 * (ER_RESOURCE_GROUP_NOT_EXIST for a group it does not know, ER_RESOURCE_GROUP_BIND_FAILED for
 * a system group; a disabled group is taken without a word), then sets the session value of
 * each SET_VAR variable as SET does, a refused value being a warning with the error SET would
 * raise. Every variable gets its session value back when the statement ends, whether it
 * succeeds or fails (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-set-var,
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-resource-group.
 *
 * @visibility MySqlMemory
 */
final class Application
{
    /**
     * @var list<array{int, string}> The warnings to raise, each its error number and message, in order
     */
    public array $warnings = [];

    /**
     * @var array<string, array{bool, string|int|null}> The session value each set variable had before, by name: whether it had one, and the value
     */
    public array $saved = [];

    /**
     * Applies the hints that count to a session; a prepared statement that runs keeps only the warnings about the values SET_VAR refuses.
     *
     * @param bool $replayed Whether the statement runs a prepared statement, whose other warnings were raised when it was prepared
     */
    public function apply(Registration $registration, Session $session, bool $replayed = false): self
    {
        $this->warnings = $replayed ? [] : $registration->warnings;
        $group = $registration->group;
        if ($group !== null) {
            $found = $session->instance->registry->resourceGroups->find($group->group);
            if ($found === null) {
                $this->warnings[] = [AdministrationError::ResourceGroupMissing->number(), AdministrationError::ResourceGroupMissing->message($group->group)];
            } elseif ($found->system) {
                $this->warnings[] = [AdministrationError::ResourceGroupBindFailed->number(), AdministrationError::ResourceGroupBindFailed->message($group->group, (string) $session->id, "System resource group can't be bound with a session thread")];
            }
        }
        if ($registration->variables === []) {
            return $this;
        }
        $diagnostics = new Diagnostics();
        $assigner = new Assigner($session->variables, new Context($session->modes(), $diagnostics, $session->variables, $session->variables->instant()));
        foreach ($registration->variables as $name => $hint) {
            $this->saved[$name] ??= [array_key_exists($name, $session->variables->session), $session->variables->session[$name] ?? null];
            [$value, $domain] = self::value($hint->value);
            try {
                $assigner->assign($name, Scope::Session, $value, $domain);
            } catch (SqlError $error) {
                $diagnostics->warning($error->error, $error->getMessage());
            }
        }
        foreach ($diagnostics->conditions as [, $code, $message]) {
            $this->warnings[] = [$code, $message];
        }

        return $this;
    }

    /**
     * Raises the warnings of the hints in the diagnostics area of the statement.
     */
    public function report(Session $session): void
    {
        foreach ($this->warnings as [$code, $message]) {
            $session->diagnostics->warning($code, $message);
        }
    }

    /**
     * Gives each variable a SET_VAR hint set its session value back.
     */
    public function restore(Session $session): void
    {
        foreach ($this->saved as $name => [$had, $value]) {
            if ($had) {
                $session->variables->session[$name] = $value;
            } else {
                unset($session->variables->session[$name]);
            }
        }
        $this->saved = [];
    }

    /**
     * Answers the value a SET_VAR hint assigns and its type: an integer, a decimal, or a string for a word or a quoted string.
     *
     * @return array{int|string, Domain}
     */
    public static function value(HintLiteral $value): array
    {
        return match ($value->kind) {
            HintLiteralKind::Integer => [strlen($value->value) < 19 || (strlen($value->value) === 19 && strcmp($value->value, (string) PHP_INT_MAX) <= 0) ? (int) $value->value : $value->value, Domain::integer(unsigned: true)],
            HintLiteralKind::Decimal => [$value->value, Domain::decimal(max(1, strlen($value->value) - 1), strlen($value->value) - (int) strpos($value->value, '.') - 1)],
            HintLiteralKind::Word, HintLiteralKind::Text => [$value->value, Domain::string(mb_strlen($value->value), Collation::known('utf8mb4_0900_ai_ci'))],
        };
    }
}
