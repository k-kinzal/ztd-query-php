<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Implements the My Sql Foreign Key Definition Parser contract for MySQL.
 */
final class MySqlForeignKeyDefinitionParser
{
    /**
     * @return array<string, ForeignKeyDefinition>
     */
    public function parseCreateTable(
        string $sql,
    ): array {
        $lexerProfile = MySqlLexerProfile::create();
        $body = (new Schema\ForeignKey\DefinitionReader())->tableBody($sql, $lexerProfile);
        if ($body === null) {
            return [];
        }

        $foreignKeys = [];
        foreach (SqlTokenStream::tokenize($body, $lexerProfile)->splitTopLevel() as $entry) {
            $stream = SqlTokenStream::tokenize($entry, $lexerProfile);
            $first = $stream->identifierAt();
            $firstKeyword = $stream->firstTopLevelKeyword();
            $inlineColumn = $first !== null && !in_array(
                $firstKeyword,
                ['CONSTRAINT', 'FOREIGN', 'PRIMARY', 'UNIQUE', 'CHECK', 'EXCLUDE'],
                true,
            ) ? $first['name'] : null;
            $name = sprintf('foreign_%d', count($foreignKeys));
            $definition = (new Schema\ForeignKey\DefinitionReader())->parseEntry($stream, $name, $inlineColumn);
            if ($definition === null) {
                continue;
            }

            $foreignKeys[$definition['name']] = $definition['foreignKey'];
        }

        return $foreignKeys;
    }

}
