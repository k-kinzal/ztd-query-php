<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption;
use SqlSemantics\Rendering\Output;

/**
 * Checks, derives and writes the option list of a tablespace, undo tablespace or log file group statement.
 *
 * Rule: MYSQL-STORAGE-OPTIONS-001. A statement accepts the options its
 * grammar rules list in some release (the constants of this class); the
 * options are kept and written in order, separated by spaces (the commas
 * between them are optional). While it parses, the server rejects a second
 * NODEGROUP (unless the first is the undefined group 65535), COMMENT,
 * ENGINE or FILE_BLOCK_SIZE (unless the first is 0) with
 * ER_FILEGROUP_OPTION_ONLY_ONCE, and in MySQL 5.x a NO_WAIT while NO_WAIT
 * is in effect; a size word other than digits with one K, M or G
 * multiplier with ER_WRONG_SIZE_NUMBER; a number of 2^31 or more before the
 * multiplier with ER_SIZE_OVERFLOW_ERROR; and a decimal or floating size or
 * node group with ER_ONLY_INTEGERS_ALLOWED. Each is a diagnostic.
 * Terminates: one pass over the options.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StorageOptions
{
    /**
     * The options of CREATE TABLESPACE.
     */
    public const TABLESPACE = ['INITIAL_SIZE', 'AUTOEXTEND_SIZE', 'MAX_SIZE', 'EXTENT_SIZE', 'NODEGROUP', 'ENGINE', 'WAIT', 'COMMENT', 'FILE_BLOCK_SIZE', 'ENCRYPTION', 'ENGINE_ATTRIBUTE'];

    /**
     * The options of ALTER TABLESPACE.
     */
    public const ALTER_TABLESPACE = ['INITIAL_SIZE', 'AUTOEXTEND_SIZE', 'MAX_SIZE', 'ENGINE', 'WAIT', 'ENCRYPTION', 'ENGINE_ATTRIBUTE'];

    /**
     * The options of ALTER TABLESPACE … CHANGE DATAFILE (MySQL 5.x).
     */
    public const CHANGE_DATAFILE = ['INITIAL_SIZE', 'AUTOEXTEND_SIZE', 'MAX_SIZE'];

    /**
     * The options of the undo tablespace statements.
     */
    public const UNDO_TABLESPACE = ['ENGINE'];

    /**
     * The options of CREATE LOGFILE GROUP.
     */
    public const LOGFILE_GROUP = ['INITIAL_SIZE', 'UNDO_BUFFER_SIZE', 'REDO_BUFFER_SIZE', 'NODEGROUP', 'ENGINE', 'WAIT', 'COMMENT'];

    /**
     * The options of ALTER LOGFILE GROUP.
     */
    public const ALTER_LOGFILE_GROUP = ['INITIAL_SIZE', 'ENGINE', 'WAIT'];

    /**
     * The options of DROP TABLESPACE and DROP LOGFILE GROUP.
     */
    public const DROP = ['ENGINE', 'WAIT'];

    /**
     * The options the server accepts once.
     */
    private const ONCE = ['NODEGROUP' => true, 'COMMENT' => true, 'ENGINE' => true, 'FILE_BLOCK_SIZE' => true];

    /**
     * Checks an option list against the options a statement accepts.
     *
     * @param list<StorageOption> $options
     * @param list<string> $accepted The keywords of the accepted options
     * @param int $minimum The least number of options
     * @return list<StorageOption>
     */
    public function checked(array $options, array $accepted, int $minimum = 0): array
    {
        $checked = Check::listOf($options, StorageOption::class, 'The statement takes a list of storage options.', $minimum);
        foreach ($checked as $option) {
            Check::input(in_array($option->keyword(), $accepted, true), 'The statement takes no ' . $option->keyword() . ' option.');
        }

        return $checked;
    }

    /**
     * Reports the options the server rejects while it parses the statement.
     *
     * @param list<StorageOption> $options
     */
    public function derive(Derivation $derivation, array $options): void
    {
        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $set = [];
        $wait = true;
        $magnitudes = new Magnitudes();
        foreach ($options as $option) {
            $keyword = $option->keyword();
            if (isset(self::ONCE[$keyword], $set[$keyword])) {
                $derivation->report(new StorageProblem(StorageRule::RepeatedOption, $keyword));
            }
            if (isset(self::ONCE[$keyword]) && !$this->unset($option)) {
                $set[$keyword] = true;
            }
            if ($option instanceof WaitOption) {
                if ($legacy && !$option->wait && !$wait) {
                    $derivation->report(new StorageProblem(StorageRule::RepeatedOption, 'NO_WAIT'));
                }
                $wait = $option->wait;
            }
            if ($option instanceof SizeOption) {
                $this->size($derivation, $keyword, $option->size);
            }
            if ($option instanceof NodegroupOption) {
                $magnitudes->integer($derivation, $option->group);
            }
        }
    }

    /**
     * Tells whether an option leaves its setting at the server's "not set" value: node group 65535 or block size 0.
     */
    public function unset(StorageOption $option): bool
    {
        $magnitudes = new Magnitudes();
        if ($option instanceof NodegroupOption) {
            return $magnitudes->integral($option->group) && $magnitudes->atMost($option->group, '65535') && !$magnitudes->atMost($option->group, '65534');
        }

        return $option instanceof SizeOption && $option->kind === SizeOptionKind::FileBlock && $option->size->number !== null
            && $magnitudes->integral($option->size->number) && $magnitudes->atMost($option->size->number, '0');
    }

    /**
     * Reports a size the server rejects.
     */
    public function size(Derivation $derivation, string $keyword, ByteSize $size): void
    {
        if ($size->number !== null) {
            (new Magnitudes())->integer($derivation, $size->number);

            return;
        }
        $word = $size->word === null ? '' : $size->word->value;
        if (preg_match('/\A([0-9]*)[KMGkmg]\z/', $word, $match) !== 1) {
            $derivation->report(new StorageProblem(StorageRule::WrongSize, $keyword));

            return;
        }
        $digits = ltrim($match[1], '0');
        if (strlen($digits) > 10 || (strlen($digits) === 10 && strcmp($digits, '2147483648') >= 0)) {
            $derivation->report(new StorageProblem(StorageRule::SizeOverflow, $keyword));
        }
    }

    /**
     * Writes the options in order, separated by spaces.
     *
     * @param list<StorageOption> $options
     */
    public function render(Output $out, array $options): void
    {
        foreach ($options as $option) {
            $out->node($option);
        }
    }
}
