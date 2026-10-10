<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;

/**
 * The information functions: DATABASE, USER, CURRENT_ROLE, VERSION, CONNECTION_ID, LAST_INSERT_ID, ROW_COUNT, FOUND_ROWS, CHARSET, COLLATION and COERCIBILITY.
 *
 * CHARSET() and COLLATION() name the character set and collation of a string or JSON value, and
 * binary for any other value (verified on a live 8.4 server).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Introspection
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {

        return [
            new Routine('DATABASE', 0, 0, fn (Frame $f): ?string => $f->context->variables->database === '' ? null : $f->context->variables->database),
            new Routine('SCHEMA', 0, 0, fn (Frame $f): ?string => $f->context->variables->database === '' ? null : $f->context->variables->database),
            new Routine('USER', 0, 0, fn (Frame $f): string => $f->context->variables->account),
            new Routine('SESSION_USER', 0, 0, fn (Frame $f): string => $f->context->variables->account),
            new Routine('SYSTEM_USER', 0, 0, fn (Frame $f): string => $f->context->variables->account),
            new Routine('CURRENT_USER', 0, 0, fn (Frame $f): string => $f->context->variables->definer),
            new Routine('CURRENT_ROLE', 0, 0, fn (Frame $f): string => $this->currentRole($f->context->variables->roles)),
            new Routine('VERSION', 0, 0, fn (Frame $f): string => (string) $f->context->variables->read('version')),
            new Routine('CONNECTION_ID', 0, 0, fn (Frame $f): int => (int) $f->context->variables->read('pseudo_thread_id')),
            new Routine('LAST_INSERT_ID', 0, 1, $this->lastInsertId(...)),
            new Routine('ROW_COUNT', 0, 0, fn (Frame $f): int => $f->context->variables->rowCount),
            new Routine('FOUND_ROWS', 0, 0, fn (Frame $f): int => $f->context->variables->foundRows),
            new Routine('CHARSET', 1, 1, fn (Frame $f, array $a): string => $this->textual($a[0]) ? $a[0]->domain()->collation->charset->nameIn($f->context->modes->release) : 'binary'),
            new Routine('COLLATION', 1, 1, fn (Frame $f, array $a): string => $this->textual($a[0]) ? $a[0]->domain()->collation->nameIn($f->context->modes->release) : 'binary'),
            new Routine('COERCIBILITY', 1, 1, fn (Frame $f, array $a): int => $this->coercibility($a[0])),
        ];
    }

    /**
     * Tells whether a value has a character set of its own: a string or a JSON value; any other value is binary.
     */
    public function textual(Evaluable $argument): bool
    {
        $kind = $argument->domain()->kind;

        return $kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::String || $kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Json;
    }

    /**
     * COERCIBILITY(): how strongly the collation of a value holds.
     *
     * NULL is ignorable (6), a number or temporal value numeric (5), a user variable implicit (2)
     * whatever it holds, and a string or JSON value has the coercibility of its type; a string
     * computed from numbers only is coercible (4) (verified on a live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_coercibility.
     */
    public function coercibility(Evaluable $argument): int
    {
        $origin = $argument;
        while ($origin instanceof \MySqlMemory\Evaluation\Leaf\Retyped) {
            $origin = $origin->evaluable;
        }
        $domain = $argument->domain();

        return match (true) {
            $origin instanceof \MySqlMemory\Evaluation\Leaf\UserVariableRead => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Implicit->value,
            $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Ignorable->value,
            !$this->textual($argument) => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Numeric->value,
            $domain->coercibility === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Numeric => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible->value,
            default => $domain->coercibility->value,
        };
    }

    /**
     * CURRENT_ROLE(): the active roles quoted as identifiers and ordered by user name, then host name, as bytes; NONE when none is active.
     *
     * Verified on a live 8.4 server.
     *
     * @param list<\MySqlMemory\Account\Identity> $roles
     */
    public function currentRole(array $roles): string
    {
        usort($roles, static fn (\MySqlMemory\Account\Identity $a, \MySqlMemory\Account\Identity $b): int => $a->user === $b->user ? strcmp($a->host, $b->host) : strcmp($a->user, $b->user));

        return $roles === [] ? 'NONE' : implode(',', array_map(static fn (\MySqlMemory\Account\Identity $role): string => $role->backquoted(), $roles));
    }

    /**
     * LAST_INSERT_ID(): the first value generated by the last insert; LAST_INSERT_ID(n) also sets it.
     *
     * @param list<Evaluable> $arguments
     */
    public function lastInsertId(Frame $frame, array $arguments, Domain $result): ?int
    {
        $variables = $frame->context->variables;
        if ($arguments === []) {
            return $variables->lastInsertId;
        }
        $value = Convert::toInteger($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context, true);
        if ($value !== null) {
            $variables->lastInsertId = $value;
            $variables->setByFunction = true;
        }

        return $value;
    }
}
