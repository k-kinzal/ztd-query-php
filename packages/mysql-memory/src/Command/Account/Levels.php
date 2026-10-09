<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Account;

use MySqlMemory\Account\Catalog;
use MySqlMemory\Account\Grants;
use MySqlMemory\Account\Privileges;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Grantable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Reads the privileges and the level of GRANT and REVOKE as the server does.
 *
 * A level is the global level, a database, a table, or a procedure or function. The server
 * refuses, in order: a role written in a privilege list and a privilege written in a role list
 * (a syntax error), a privilege, column list or object kind the level does not take
 * (ER_ILLEGAL_GRANT_FOR_TABLE), a table or routine that does not exist when GRANT names it, a
 * dynamic privilege below the global level (ER_ILLEGAL_PRIVILEGE_LEVEL, naming the privilege at
 * a database and the object at a table or routine), and a column the table does not have.
 * After the accounts, a privilege a database does not take is ER_WRONG_USAGE. ALL grants every
 * privilege the level takes, at the global level the dynamic ones too (verified on a live 8.4
 * server), of the privileges the release knows (see Catalog).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html#grant-privilege-levels.
 *
 * @visibility MySqlMemory
 */
final class Levels
{
    /**
     * @param \SqlSemantics\Contract\GrammarRelease $release The release whose privileges ALL names
     */
    public function __construct(public readonly \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847)
    {
    }

    /**
     * Answers the level a statement names: GLOBAL, DATABASE, TABLE, PROCEDURE or FUNCTION, the database and the object.
     *
     * @return array{string, string, string}
     *
     * @throws SqlError When the level needs the current database and there is none
     */
    public function target(ObjectKind $kind, PrivilegeLevel $level, Session $session): array
    {
        if ($level instanceof GlobalLevel) {
            return ['GLOBAL', '', ''];
        }
        $database = match (true) {
            $level instanceof DatabaseLevel => $level->database->value,
            $level instanceof ObjectLevel => $level->name->schema->value ?? $session->variables->database,
            default => $session->variables->database,
        };
        if ($database === '') {
            throw QueryError::NoDatabase->error();
        }
        if (!$level instanceof ObjectLevel) {
            return ['DATABASE', $database, ''];
        }

        return [match ($kind) {
            ObjectKind::Table => 'TABLE',
            ObjectKind::Procedure => 'PROCEDURE',
            ObjectKind::Function => 'FUNCTION',
        }, $database, $level->name->name->value];
    }

    /**
     * Raises the problems the server finds while it parses the statement: a role in a privilege list or a privilege in a role list, and a privilege the level does not take.
     *
     * @throws SqlError When the statement has such a problem
     */
    public function parsed(Operation $operation, Session $session): void
    {
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof RoleOrPrivilegeMismatch) {
                $near = $this->near($session->text, $diagnostic->item);

                throw new SqlError(StatementError::ParseError, ($diagnostic->roleExpected ? 'Illegal authorization identifier' : 'Illegal privilege identifier') . " near '" . mb_strcut($near, 0, 80, 'UTF-8') . "' at line 1");
            }
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof MisplacedPrivilege && $diagnostic->error === 'ER_ILLEGAL_GRANT_FOR_TABLE') {
                throw AccountError::IllegalGrantForTable->error();
            }
            if ($diagnostic instanceof MisplacedPrivilege && $diagnostic->error === 'ER_PARSE_ERROR') {
                throw StatementError::ParseError->error('', 1);
            }
        }
    }

    /**
     * Answers the text of a statement from where an item is written, as a syntax error quotes it.
     */
    public function near(string $text, string $item): string
    {
        $role = str_contains($item, '@');
        $word = (string) preg_replace('/[\s(@].*\z/s', '', $item);
        $pattern = '/[`\'"]?(?<![A-Za-z0-9_$])' . preg_quote($word, '/') . ($role ? '[`\'"]?\s*@' : '(?![A-Za-z0-9_$])') . '/i';
        if ($word === '' || preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE, min(strlen($text), 5)) !== 1) {
            return '';
        }

        return substr($text, $match[0][1]);
    }

    /**
     * Raises the problems of the objects a GRANT names: a table or routine that does not exist, a dynamic privilege below the global level, and a column the table does not have.
     *
     * @param list<Grantable> $privileges
     * @param array{string, string, string} $target
     *
     * @throws SqlError When an object does not exist or a privilege does not fit
     */
    public function objects(Operation $operation, Session $session, array $privileges, array $target): void
    {
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof MissingTable) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        if ($target[0] === 'PROCEDURE' || $target[0] === 'FUNCTION') {
            $schema = $session->instance->dictionary->schema($target[1]);
            $routines = $target[0] === 'PROCEDURE' ? $schema->procedures ?? [] : $schema->functions ?? [];
            if (!isset($routines[mb_strtolower($target[2], 'UTF-8')])) {
                throw ProgramError::RoutineMissing->error($target[0], $target[2]);
            }
        }
        $this->dynamic($privileges, $target, $session, false);
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownGrantColumn) {
                throw QueryError::BadField->error($diagnostic->column->value, $target[2]);
            }
        }
    }

    /**
     * Refuses a dynamic privilege below the global level, or under REVOKE IF EXISTS warns of it.
     *
     * @param list<Grantable> $privileges
     * @param array{string, string, string} $target
     *
     * @throws SqlError When the privilege is refused
     */
    public function dynamic(array $privileges, array $target, Session $session, bool $lenient): void
    {
        if ($target[0] === 'GLOBAL') {
            return;
        }
        foreach ($privileges as $privilege) {
            if (!$privilege instanceof DynamicPrivilege) {
                continue;
            }
            $name = $target[0] === 'DATABASE' ? strtoupper($privilege->name->value) : $target[2];
            if (!$lenient) {
                throw AccountError::IllegalPrivilegeLevel->error($name);
            }
            $session->diagnostics->warning(AccountError::IllegalPrivilegeLevel, AccountError::IllegalPrivilegeLevel->message($name));
        }
    }

    /**
     * Refuses a privilege a database does not take, which the server finds after it has found the accounts.
     *
     * @throws SqlError When a privilege does not fit
     */
    public function usage(Operation $operation): void
    {
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof MisplacedPrivilege && $diagnostic->error === 'ER_WRONG_USAGE') {
                throw StatementError::WrongUsage->error('DB GRANT', 'GLOBAL PRIVILEGES');
            }
        }
    }

    /**
     * Reads a privilege list for a level: the static privileges, the column privileges, the dynamic privileges, whether GRANT OPTION is named, and whether ALL is.
     *
     * @param list<Grantable> $privileges
     * @return array{list<string>, array<string, list<string>>, list<string>, bool, bool}
     */
    public function read(array $privileges, string $level): array
    {
        $names = [];
        $columns = [];
        $dynamic = [];
        $option = false;
        $all = false;
        foreach ($privileges as $privilege) {
            if ($privilege instanceof AllPrivileges) {
                $all = true;
                $names = $this->all($level);
                $dynamic = $level === 'GLOBAL' ? (new Catalog($this->release))->dynamics() : [];
            } elseif ($privilege instanceof StaticPrivilege && $privilege->kind === PrivilegeKind::GrantOption) {
                $option = true;
            } elseif ($privilege instanceof StaticPrivilege && $privilege->columns !== []) {
                $columns[$privilege->kind->value] = array_values(array_unique([...$columns[$privilege->kind->value] ?? [], ...array_map(static fn ($column): string => $column->value, $privilege->columns)]));
            } elseif ($privilege instanceof StaticPrivilege && $privilege->kind !== PrivilegeKind::Usage) {
                $names[] = $privilege->kind->value;
            } elseif ($privilege instanceof DynamicPrivilege) {
                $dynamic[] = strtoupper($privilege->name->value);
            }
        }

        return [array_values(array_unique($names)), $columns, array_values(array_unique($dynamic)), $option, $all];
    }

    /**
     * Answers the static privileges ALL names at a level.
     *
     * @return list<string>
     */
    public function all(string $level): array
    {
        return match ($level) {
            'GLOBAL' => (new Catalog($this->release))->statics(),
            'DATABASE' => Catalog::DATABASE,
            'TABLE' => Catalog::TABLE,
            default => ['EXECUTE', 'ALTER ROUTINE'],
        };
    }

    /**
     * Answers the privileges an account holds at a level, kept from now on, or null when none are kept and none are to be.
     *
     * @param array{string, string, string} $target
     */
    public function at(Grants $grants, array $target, bool $create): ?Privileges
    {
        return match ($target[0]) {
            'GLOBAL' => $grants->global,
            'DATABASE' => $create ? $grants->database($target[1]) : $grants->databases[$target[1]] ?? null,
            'TABLE' => $create ? $grants->table($target[1], $target[2]) : $grants->findTable($target[1], $target[2]),
            default => $create ? $grants->routine($target[0], $target[1], $target[2]) : $grants->findRoutine($target[0], $target[1], $target[2]),
        };
    }
}
