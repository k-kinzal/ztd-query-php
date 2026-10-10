<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW PROCEDURE STATUS and SHOW FUNCTION STATUS: the routines of every database, by database and name.
 *
 * The rows are read from INFORMATION_SCHEMA.ROUTINES; LIKE matches the name. The catalog
 * includes the public metadata of the installed sys routines.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-procedure-status.html.
 *
 * @visibility MySqlMemory
 */
final class ShowRoutinesCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Lists the routines.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowProcedureStatus || $statement instanceof ShowFunctionStatus);
        $function = $statement instanceof ShowFunctionStatus;
        $language = !in_array($session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810], true);
        $rows = [];
        foreach ($session->instance->dictionary->schemas as $schema) {
            foreach (RoutineCommand::routines($schema, $function) as $routine) {
                $rows[] = [$routine->schema, $routine->name, $routine->kind(), ...($language ? ['SQL'] : []), $routine->definer[0] . '@' . $routine->definer[1], \MySqlMemory\System\Program\RoutineTimes::local($routine->modified, $context), \MySqlMemory\System\Program\RoutineTimes::local($routine->created, $context), $routine->security, $routine->comment, ...$routine->charsets];
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left[0], strtolower($left[1])] <=> [$right[0], strtolower($right[1])]);

        return (new Listing($this->headings($language, $session->settings()->release(), RoutineLookup::single($statement->filter, $operation->facts))))->result($rows, $operation, $session, $context, $connection, $statement->filter, 1);
    }

    /**
     * Answers the columns of the statement, as the server describes them.
     *
     * @param bool $language Whether the release lists the language of each routine, as MySQL 8.2 and later do (verified on live 8.0, 8.4 and 9.1 servers)
     * @return list<Heading>
     */
    public function headings(bool $language = true, GrammarRelease $release = GrammarRelease::MySql847, bool $direct = false): array
    {
        $table = 'ROUTINES';
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $direct = $direct && !$legacy;
        $kind = $legacy ? Field::VarString : Field::String;
        $time = $legacy ? Field::DateTime : Field::Timestamp;
        $definer = $legacy ? ($release === GrammarRelease::MySql5651 ? 77 : 93) : 288;
        $characters = $legacy ? 32 : 64;

        return [
            Heading::text('Db', Field::VarString, 64, 4225, 0, 'Db', $table, 'schemata'),
            Heading::text('Name', Field::VarString, 64, 4097, 0, 'Name', $table, 'routines'),
            Heading::text('Type', $kind, 9, 4481, 0, 'Type', $table, 'routines'),
            ...($language ? [Heading::text('Language', Field::VarString, 64, 129, 0, 'Language', $table, 'routines')] : []),
            Heading::text('Definer', Field::VarString, $definer, 4225 | ($direct ? 8 : 0), 0, 'Definer', $table, 'routines'),
            new Heading('Modified', $time, 19, 4225, 0, $direct, 'Modified', $table, 'routines'),
            new Heading('Created', $time, 19, 4225, 0, $direct, 'Created', $table, 'routines'),
            Heading::text('Security_type', $kind, 7, 4481, 0, 'Security_type', $table, 'routines'),
            Heading::text('Comment', Field::Blob, $legacy ? 196605 : 65535, 4241, 0, 'Comment', $table, 'routines'),
            Heading::text('character_set_client', Field::VarString, $characters, 4097 | ($direct ? 4 : 0), 0, 'character_set_client', $table, 'character_sets'),
            Heading::text('collation_connection', Field::VarString, $characters, 4097 | ($direct ? 4 : 0), 0, 'collation_connection', $table, 'collations'),
            Heading::text('Database Collation', Field::VarString, $characters, 4097 | ($direct ? 4 : 0), 0, 'Database Collation', $table, 'collations'),
        ];
    }
}
