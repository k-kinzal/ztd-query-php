<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfiles;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW PROFILES and SHOW PROFILE: the statements the session profiled, and the stages of one of them.
 *
 * Both are deprecated and warn so, but for a LIMIT operand of SHOW PROFILE naming a variable,
 * ER_SP_UNDECLARED_VAR, found first (verified on live 8.0, 8.4 and 9.1 servers). The emulator does not profile statements, as a session that
 * has not set profiling does not, so they list none. SHOW PROFILE answers the status and
 * duration of each stage and the columns of the sections it names, read from
 * INFORMATION_SCHEMA.PROFILING (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-profile.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-profiles.html.
 *
 * @visibility MySqlMemory
 */
final class ShowProfilesCommand implements Command
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
     * Lists the profiles, or the stages of one.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowProfiles || $statement instanceof ShowProfile);
        $limit = $statement instanceof ShowProfile ? $statement->limit : null;
        foreach ($limit instanceof RowLimit ? [$limit->offset, $limit->count] : [] as $operand) {
            if ($operand instanceof ProgramVariable) {
                throw ProgramError::UndeclaredVariable->error($operand->name->value);
            }
        }
        $name = $statement instanceof ShowProfiles ? 'SHOW PROFILES' : 'SHOW PROFILE';
        $context->warning(StatementError::DeprecatedSyntax, $name, 'Performance Schema');
        if ($statement instanceof ShowProfiles) {
            $flags = ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::Binary->value | ColumnFlag::Numeric->value;
            $headings = [
                new Heading('Query_ID', Field::Long, 11, $flags),
                new Heading('Duration', Field::Double, 9, $flags),
                Heading::text('Query', Field::VarString, 40, ColumnFlag::NotNull->value, 31),
            ];

            return (new Listing($headings))->sent([], $context);
        }

        return (new Listing($this->headings($statement->sections)))->sent([], $context);
    }

    /**
     * Answers the columns of SHOW PROFILE: the status and duration, then the columns of the sections named, in the order of the table.
     *
     * @param list<ProfileSection> $sections
     * @return list<Heading>
     */
    public function headings(array $sections): array
    {
        $table = 'PROFILING';
        $schema = 'information_schema';
        $time = static fn (string $name, string $original): Heading => new Heading($name, Field::NewDecimal, 11, 0, 6, false, $original, $table, $table, $schema);
        $count = static fn (string $name, string $original): Heading => new Heading($name, Field::Long, 21, ColumnFlag::Numeric->value, 0, false, $original, $table, $table, $schema);
        $all = [
            2 => $time('CPU_user', 'CPU_USER'),
            3 => $time('CPU_system', 'CPU_SYSTEM'),
            4 => $count('Context_voluntary', 'CONTEXT_VOLUNTARY'),
            5 => $count('Context_involuntary', 'CONTEXT_INVOLUNTARY'),
            6 => $count('Block_ops_in', 'BLOCK_OPS_IN'),
            7 => $count('Block_ops_out', 'BLOCK_OPS_OUT'),
            8 => $count('Messages_sent', 'MESSAGES_SENT'),
            9 => $count('Messages_received', 'MESSAGES_RECEIVED'),
            10 => $count('Page_faults_major', 'PAGE_FAULTS_MAJOR'),
            11 => $count('Page_faults_minor', 'PAGE_FAULTS_MINOR'),
            12 => $count('Swaps', 'SWAPS'),
            13 => Heading::text('Source_function', Field::VarString, 30, 0, 0, 'SOURCE_FUNCTION', $table, $table, $schema),
            14 => Heading::text('Source_file', Field::VarString, 20, 0, 0, 'SOURCE_FILE', $table, $table, $schema),
            15 => $count('Source_line', 'SOURCE_LINE'),
        ];
        $positions = [];
        foreach ($sections as $section) {
            foreach ($section->positions() as $position) {
                $positions[$position] = true;
            }
        }
        ksort($positions);
        $headings = [
            Heading::text('Status', Field::VarString, 30, ColumnFlag::NotNull->value, 0, 'STATE', $table, $table, $schema),
            new Heading('Duration', Field::NewDecimal, 11, ColumnFlag::NotNull->value, 6, false, 'DURATION', $table, $table, $schema),
        ];
        foreach (array_keys($positions) as $position) {
            $headings[] = $all[$position];
        }

        return $headings;
    }
}
