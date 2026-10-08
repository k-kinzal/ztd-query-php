<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW COLLATION and SHOW CHARACTER SET: the collations and character sets of the server, in name order.
 *
 * They are those of the catalog of the emulated release: each collation with its character set,
 * id, whether it is the default of its character set and its pad attribute; each character set
 * with its description, default collation and longest character. Every collation is compiled
 * in. The rows are read from INFORMATION_SCHEMA, which the column metadata names, and the
 * collations come in the order of their names in utf8mb3_general_ci, which compares letters in
 * upper case. LIKE matches the names without regard to case (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-collation.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-character-set.html.
 *
 * @visibility MySqlMemory
 */
final class ShowCollationCommand implements Command
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
     * Lists the collations or the character sets.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowCollation || $statement instanceof ShowCharacterSet);
        $release = $session->settings()->release();
        if ($statement instanceof ShowCollation) {
            return (new Listing($this->collationHeadings()))->result($this->collations($release), $operation, $session, $context, $connection, $statement->filter);
        }

        return (new Listing($this->charsetHeadings()))->result($this->charsets($release), $operation, $session, $context, $connection, $statement->filter);
    }

    /**
     * Answers the rows of the collations of a release.
     *
     * @return list<list<int|string>>
     */
    public function collations(GrammarRelease $release): array
    {
        $catalog = Catalog::shared();
        $defaults = $catalog->defaults[$release->value] ?? [];
        $sortLengths = ServerCatalog::shared()->collations;
        $rows = [];
        foreach ($catalog->collations as $name => $collation) {
            if (!in_array($release->value, $catalog->releases[$name] ?? [], true)) {
                continue;
            }
            $charset = $collation->charset->name;
            $rows[strtoupper($name)] = [$name, $charset, $collation->id, ($defaults[$charset] ?? null) === $name ? 'Yes' : '', 'Yes', $sortLengths[$name] ?? 1, $collation->padSpace ? 'PAD SPACE' : 'NO PAD'];
        }
        ksort($rows, SORT_STRING);

        return array_values($rows);
    }

    /**
     * Answers the rows of the character sets of a release.
     *
     * @return list<list<int|string>>
     */
    public function charsets(GrammarRelease $release): array
    {
        $catalog = Catalog::shared();
        $descriptions = ServerCatalog::shared()->charsets;
        $rows = [];
        foreach ($catalog->defaults[$release->value] ?? [] as $name => $default) {
            $rows[$name] = [$name, $descriptions[$name] ?? '', $default, $catalog->charsets[$name]->maxLength ?? 1];
        }
        ksort($rows, SORT_STRING);

        return array_values($rows);
    }

    /**
     * Answers the columns of SHOW COLLATION.
     *
     * @return list<Heading>
     */
    public function collationHeadings(): array
    {
        $table = 'COLLATIONS';
        $schema = 'information_schema';
        $name = ColumnFlag::NotNull->value | ColumnFlag::NoDefaultValue->value;

        return [
            Heading::text('Collation', Field::VarString, 64, $name, 0, 'Collation', $table, $table, $schema),
            Heading::text('Charset', Field::VarString, 64, $name, 0, 'Charset', $table, $table, $schema),
            new Heading('Id', Field::LongLong, 20, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::Numeric->value, 0, false, 'Id', $table, $table, $schema),
            Heading::text('Default', Field::VarString, 3, ColumnFlag::NotNull->value, 0, 'Default', $table, $table, $schema),
            Heading::text('Compiled', Field::VarString, 3, ColumnFlag::NotNull->value, 0, 'Compiled', $table, $table, $schema),
            new Heading('Sortlen', Field::Long, 10, $name | ColumnFlag::Unsigned->value | ColumnFlag::Numeric->value, 0, false, 'Sortlen', $table, $table, $schema),
            Heading::text('Pad_attribute', Field::String, 9, $name | ColumnFlag::Binary->value | ColumnFlag::Enum->value, 0, 'Pad_attribute', $table, $table, $schema),
        ];
    }

    /**
     * Answers the columns of SHOW CHARACTER SET.
     *
     * @return list<Heading>
     */
    public function charsetHeadings(): array
    {
        $table = 'CHARACTER_SETS';
        $schema = 'information_schema';
        $key = ColumnFlag::NotNull->value | ColumnFlag::UniqueKey->value | ColumnFlag::NoDefaultValue->value | 16384;

        return [
            Heading::text('Charset', Field::VarString, 64, $key, 0, 'Charset', $table, 'cs', $schema),
            Heading::text('Description', Field::VarString, 2048, ColumnFlag::NotNull->value | ColumnFlag::NoDefaultValue->value, 0, 'Description', $table, 'cs', $schema),
            Heading::text('Default collation', Field::VarString, 64, $key, 0, 'Default collation', $table, 'col', $schema),
            new Heading('Maxlen', Field::Long, 10, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value | ColumnFlag::NoDefaultValue->value | ColumnFlag::Numeric->value, 0, false, 'Maxlen', $table, 'cs', $schema),
        ];
    }
}
