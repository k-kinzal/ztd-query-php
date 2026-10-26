<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\ColumnText;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Declared;
use Override;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\DataAccess;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\SqlSecurity;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE PROCEDURE, CREATE FUNCTION and ALTER PROCEDURE or FUNCTION.
 *
 * A routine that exists is ER_SP_ALREADY_EXISTS, a note with IF NOT EXISTS. With binary
 * logging on and log_bin_trust_function_creators off, a function declared neither
 * DETERMINISTIC, NO SQL nor READS SQL DATA is refused (ER_BINLOG_UNSAFE_ROUTINE), before the
 * database is looked up. Of each kind of characteristic the last one written holds. ALTER
 * changes the data access, the security context and the comment of an existing routine.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stored-programs-logging.html.
 *
 * @visibility MySqlMemory
 */
final class RoutineCommand implements Command
{
    /**
     * @param bool $clears Whether the statement starts with an empty diagnostics area: a program whose body declares a condition or handler is created leaving the area as it was (verified on a live 8.4 server)
     */
    public function __construct(public readonly bool $clears = true)
    {
    }

    /**
     * Answers whether the statement starts with an empty diagnostics area.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return $this->clears;
    }

    /**
     * Creates or changes the routine.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        $session->transaction->commit();
        if ($statement instanceof AlterRoutine) {
            return $this->alter($statement, $session, $context->started);
        }
        assert($statement instanceof CreateProcedure || $statement instanceof CreateFunction);
        if ($statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody) {
            $unavailable = (new \MySqlMemory\Error\ProgramErrors())->language($session->settings()->release());
            if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql810) {
                throw $unavailable;
            }
            $context->warning(ProgramError::LanguageComponentUnavailable, $unavailable->getMessage());
        }
        $function = $statement instanceof CreateFunction;
        $kind = $function ? 'FUNCTION' : 'PROCEDURE';
        $database = ProgramSource::database($statement->name->schema, $session);
        ProgramSource::definer($statement->definer, $session, $context);
        $key = strtolower($statement->name->name->value);
        $schema = $session->instance->dictionary->schema($database);
        if ($schema !== null && isset(self::routines($schema, $function)[$key])) {
            if (!$statement->ifNotExists) {
                throw ProgramError::RoutineExists->error($kind, $statement->name->name->value);
            }
            $context->note(ProgramError::RoutineExists, $kind, $statement->name->name->value);

            return new Completion(0, 0, $context->diagnostics->raised());
        }
        [$access, $deterministic, $security, $comment] = $this->characteristics($statement->characteristics, ['CONTAINS SQL', false, 'DEFINER', '']);
        if ($function && !$deterministic && $access !== 'NO SQL' && $access !== 'READS SQL DATA' && ProgramSource::enabled($session->variables->read('log_bin')) && !ProgramSource::enabled($session->variables->read('log_bin_trust_function_creators'))) {
            throw ProgramError::UnsafeRoutine->error();
        }
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        $routine = $this->routine($statement, $session, $context, $schema, [$access, $deterministic, $security, $comment], ProgramText::of($operation, $session));
        if ($function) {
            $schema->functions[$key] = $routine;
        } else {
            $schema->procedures[$key] = $routine;
        }
        $session->instance->dictionary->routineGeneration++;

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Resolves the routine database, reports its name relationship and checks ENUM and SET before the body conditions.
     *
     * @throws \MySqlMemory\Error\SqlError When an enumeration repeats a member under strict mode
     */
    public function types(CreateProcedure|CreateFunction|RefusedProgramBody $statement, Session $session, Context $context): void
    {
        ProgramSource::database($statement->name->schema, $session);
        if ($statement instanceof CreateFunction && $statement->nameCollision($session->settings()->release())) {
            $context->note(\MySqlMemory\Error\Family\AdministrationError::NativeFunctionName, $statement->name->name->value);
        }
        $types = new RoutineDeclarations();
        $types->check($statement->name, $types->items($statement), $session, $context);
    }

    /**
     * Builds the stored routine from its semantic declaration and optional display spelling.
     *
     * @param array{string, bool, string, string} $characteristics The data access, determinism, security context and comment of the routine
     * @param ProgramText|null $text The display spelling; absent input renders the typed declaration
     */
    public function routine(CreateProcedure|CreateFunction $statement, Session $session, Context $context, Schema $schema, array $characteristics, ?ProgramText $text = null): Routine
    {
        [$access, $deterministic, $security, $comment] = $characteristics;
        $function = $statement instanceof CreateFunction;
        $text ??= new ProgramText($session->semantics()->context()->profile);
        $now = gmdate('Y-m-d H:i:s', (int) floor($context->started));

        return new Routine(
            $schema->name,
            $statement->name->name->value,
            ProgramSource::definer($statement->definer, $session),
            $text->parameters($statement->parameters, $function),
            $statement instanceof CreateFunction ? $this->returns($statement, $schema, $session->settings()->release()) : '',
            $text->routineBody($statement->body),
            $access,
            $deterministic,
            $security,
            $comment,
            (string) $session->variables->read('sql_mode'),
            $now,
            $now,
            ProgramSource::charsets($session, $schema->name),
            $statement,
            release: $session->settings()->release(),
        );
    }

    /**
     * Changes the characteristics of an existing routine.
     *
     * @throws \MySqlMemory\Error\SqlError When the routine does not exist
     */
    public function alter(AlterRoutine $statement, Session $session, ?float $instant = null): Reply
    {
        $database = ProgramSource::database($statement->name->schema, $session);
        $function = $statement->kind === ProgramKind::Function;
        $schema = $session->instance->dictionary->schema($database);
        $routine = $schema === null ? null : (self::routines($schema, $function)[strtolower($statement->name->name->value)] ?? null);
        if ($routine === null) {
            throw ProgramError::RoutineMissing->error($statement->kind->value, $database . '.' . $statement->name->name->value);
        }
        [$routine->access, , $routine->security, $routine->comment] = $this->characteristics($statement->characteristics, [$routine->access, $routine->deterministic, $routine->security, $routine->comment]);
        $routine->modified = gmdate('Y-m-d H:i:s', (int) floor($instant ?? $session->variables->instant()));
        $routine->metadataLoaded = false;
        $session->instance->dictionary->routineGeneration++;

        return new Completion();
    }

    /**
     * Answers the routines of a kind in a database, by lower-case name.
     *
     * @return array<string, Routine>
     */
    public static function routines(Schema $schema, bool $function): array
    {
        return $function ? $schema->functions : $schema->procedures;
    }

    /**
     * Answers the data access, determinism, security context and comment the characteristics set, from the values before them.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic> $characteristics
     * @param array{string, bool, string, string} $values
     * @return array{string, bool, string, string}
     */
    public function characteristics(array $characteristics, array $values): array
    {
        foreach ($characteristics as $characteristic) {
            if ($characteristic instanceof DataAccess) {
                $values[0] = match ($characteristic->level) {
                    AccessLevel::ContainsSql => 'CONTAINS SQL',
                    AccessLevel::NoSql => 'NO SQL',
                    AccessLevel::ReadsSqlData => 'READS SQL DATA',
                    AccessLevel::ModifiesSqlData => 'MODIFIES SQL DATA',
                };
            }
            if ($characteristic instanceof Determinism) {
                $values[1] = $characteristic->deterministic;
            }
            if ($characteristic instanceof SqlSecurity) {
                $values[2] = $characteristic->context->value;
            }
            if ($characteristic instanceof RoutineComment) {
                $values[3] = $characteristic->text->value;
            }
        }

        return $values;
    }

    /**
     * Writes the type a function returns as SHOW CREATE writes it: the type of a column, with the character set of a string and a collation that is not its default, as the release names them (verified on live 5.7.44 and 8.4 servers).
     */
    public function returns(CreateFunction $statement, Schema $schema, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): string
    {
        $default = Collation::named($schema->collation) ?? Collation::known('utf8mb4_0900_ai_ci');
        $collation = (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\ReturnCollation())->resolve($statement->returns, $statement->collation, $default, $release);
        $domain = (new Declared($default))->domain($statement->returns, $collation);
        if ($domain->kind === Kind::String && !$domain->collation->bytes()) {
            $domain = $domain->withCollation($collation, $domain->coercibility);
        }
        $text = (new ColumnText($release))->type($domain, $statement->returns);
        if (($domain->kind !== Kind::String && $domain->kind !== Kind::Json) || ($domain->collation->bytes() && !$statement->returns instanceof \SqlSemantics\Platform\MySql\Statement\Type\Enumeration) || $domain->field === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Json) {
            return $text;
        }
        $charset = $domain->collation->charset;

        return $text . ' CHARSET ' . $charset->nameIn($release) . ($charset->defaultCollation(\SqlSemantics\Contract\GrammarRelease::MySql847)->name === $domain->collation->name ? '' : ' COLLATE ' . $domain->collation->nameIn($release));
    }
}
