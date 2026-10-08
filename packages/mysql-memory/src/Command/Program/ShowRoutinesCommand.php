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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW PROCEDURE STATUS and SHOW FUNCTION STATUS: the routines of every database, by database and name.
 *
 * The rows are read from INFORMATION_SCHEMA.ROUTINES; LIKE matches the name. The emulator
 * holds no system routines, so the routines of the sys schema are not listed.
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
        $rows = [];
        foreach ($session->instance->dictionary->schemas as $schema) {
            foreach (RoutineCommand::routines($schema, $function) as $routine) {
                $rows[] = [$routine->schema, $routine->name, $routine->kind(), 'SQL', $routine->definer[0] . '@' . $routine->definer[1], $routine->modified, $routine->created, $routine->security, $routine->comment, ...$routine->charsets];
            }
        }
        usort($rows, static fn (array $left, array $right): int => [$left[0], strtolower($left[1])] <=> [$right[0], strtolower($right[1])]);

        return (new Listing($this->headings()))->result($rows, $operation, $session, $context, $connection, $statement->filter, 1);
    }

    /**
     * Answers the columns of the statement, as the server describes them.
     *
     * @return list<Heading>
     */
    public function headings(): array
    {
        $table = 'ROUTINES';

        return [
            Heading::text('Db', Field::VarString, 64, 4225, 0, 'Db', $table, 'schemata'),
            Heading::text('Name', Field::VarString, 64, 4097, 0, 'Name', $table, 'routines'),
            Heading::text('Type', Field::String, 9, 4481, 0, 'Type', $table, 'routines'),
            Heading::text('Language', Field::VarString, 64, 129, 0, 'Language', $table, 'routines'),
            Heading::text('Definer', Field::VarString, 288, 4225, 0, 'Definer', $table, 'routines'),
            new Heading('Modified', Field::Timestamp, 19, 4225, 0, false, 'Modified', $table, 'routines'),
            new Heading('Created', Field::Timestamp, 19, 4225, 0, false, 'Created', $table, 'routines'),
            Heading::text('Security_type', Field::String, 7, 4481, 0, 'Security_type', $table, 'routines'),
            Heading::text('Comment', Field::Blob, 65535, 4241, 0, 'Comment', $table, 'routines'),
            Heading::text('character_set_client', Field::VarString, 64, 4097, 0, 'character_set_client', $table, 'character_sets'),
            Heading::text('collation_connection', Field::VarString, 64, 4097, 0, 'collation_connection', $table, 'collations'),
            Heading::text('Database Collation', Field::VarString, 64, 4097, 0, 'Database Collation', $table, 'collations'),
        ];
    }
}
