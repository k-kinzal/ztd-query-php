<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parse;

use ZtdQuery\Platform\MySql\Dialect\MySqlLexerProfile;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\ReferentialAction;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Reads the foreign keys a CREATE TABLE declares.
 *
 * MySQL lets a key be declared on the column it is over or on its own, and
 * lets it go unnamed, so a key read here is given a name of its own where the
 * statement gave it none -- otherwise two unnamed keys would be one.
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
        $body = $this->tableBody($sql, $lexerProfile);
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
            $definition = $this->parseEntry($stream, $name, $inlineColumn);
            if ($definition === null) {
                continue;
            }

            $foreignKeys[$definition['name']] = $definition['foreignKey'];
        }

        return $foreignKeys;
    }

    /**
     * Answers everything a CREATE TABLE declares between its parentheses.
     *
     * @param string $sql Declaration to read
     * @param SqlLexerProfile $lexerProfile What the dialect spells things with
     *
     * @return string|null The declarations, as written, or null where the text declares no table
     */
    public function tableBody(string $sql, SqlLexerProfile $lexerProfile): ?string
    {
        $tokens = SqlTokenStream::tokenize($sql, $lexerProfile)->significantTokens();
        $first = $tokens[0] ?? null;
        if ($first === null || !$first->isKeyword('CREATE')) {
            return null;
        }

        $tableFound = false;
        $opening = null;
        foreach ($tokens as $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if (!$tableFound) {
                if ($token->isKeyword('TABLE')) {
                    $tableFound = true;
                }
                continue;
            }
            if ($opening === null) {
                if (self::isSymbol($token, '(')) {
                    $opening = $token;
                }
                continue;
            }
            if (self::isSymbol($token, ')')) {
                return substr($sql, $opening->endOffset(), $token->offset - $opening->endOffset());
            }
        }

        return null;
    }

    /**
     * Reads one declaration as a foreign key, if that is what it declares.
     *
     * A key written on the column itself names no columns of its own, so the
     * column it was written on is passed in.
     *
     * @param SqlTokenStream $stream The declaration, as tokens
     * @param string $defaultName Name to give a key the statement did not name
     * @param string|null $inlineColumn Column the declaration was written on, or null where it stands alone
     *
     * @return array{name: string, foreignKey: ForeignKeyDefinition}|null The key and its name, or null where this declares no key
     */
    public function parseEntry(
        SqlTokenStream $stream,
        string $defaultName,
        ?string $inlineColumn,
    ): ?array {
        $tokens = $stream->significantTokens();
        $references = self::keywordIndex($tokens, 'REFERENCES');
        if ($references === null) {
            return null;
        }

        $columns = $inlineColumn !== null
            ? [$inlineColumn]
            : $this->foreignKeyColumns($stream, $tokens, $references);
        if ($columns === []) {
            return null;
        }

        $referenced = $this->referencedRelation($stream, $tokens, $references + 1);
        if ($referenced === null) {
            return null;
        }

        $name = $defaultName;
        if ($tokens[0]->isKeyword('CONSTRAINT')) {
            $constraint = $stream->identifierAt(1);
            if ($constraint !== null) {
                $name = $constraint['name'];
            }
        }

        return [
            'name' => $name,
            'foreignKey' => new ForeignKeyDefinition(
                $columns,
                $referenced['table'],
                $referenced['columns'],
                $this->action($tokens, 'DELETE'),
                $this->action($tokens, 'UPDATE'),
            ),
        ];
    }

    /**
     * Answers the columns a FOREIGN KEY declaration is over.
     *
     * @param SqlTokenStream $stream The declaration, as tokens
     * @param list<SqlToken> $tokens The same tokens, with nothing insignificant left in
     * @param int $references Where REFERENCES is written
     *
     * @return list<string> The columns, or none where the declaration is not written as a FOREIGN KEY
     */
    public function foreignKeyColumns(
        SqlTokenStream $stream,
        array $tokens,
        int $references,
    ): array {
        $foreign = self::keywordIndex(array_slice($tokens, 0, $references), 'FOREIGN');
        if ($foreign === null) {
            return [];
        }

        $key = $tokens[$foreign + 1];
        if (!$key->isKeyword('KEY')) {
            return [];
        }

        $opening = $foreign + 2;
        return $this->identifierList($stream, $tokens, $opening);
    }

    /**
     * Answers the table a key points at, and the columns of it.
     *
     * A qualified name is read down to its last part, because the shadow knows
     * a table by its name and not by the schema it is written under.
     *
     * @param SqlTokenStream $stream The declaration, as tokens
     * @param list<SqlToken> $tokens The same tokens, with nothing insignificant left in
     * @param int $start Where to start reading the name
     *
     * @return array{table: string, columns: list<string>}|null The table and columns, or null where no name is written
     */
    public function referencedRelation(
        SqlTokenStream $stream,
        array $tokens,
        int $start,
    ): ?array {
        $identifier = $stream->identifierAt($start);
        if ($identifier === null) {
            return null;
        }

        $table = $identifier['name'];
        $next = $identifier['next'];
        while (self::isSymbol($tokens[$next] ?? null, '.')) {
            $component = $stream->identifierAt($next + 1);
            if ($component === null) {
                return null;
            }
            $table = $component['name'];
            $next = $component['next'];
        }

        $opening = self::symbolIndex($tokens, '(', $next);
        $columns = $opening !== null ? $this->identifierList($stream, $tokens, $opening) : [];

        return ['table' => $table, 'columns' => $columns];
    }

    /**
     * Answers the names written inside one pair of parentheses.
     *
     * @param SqlTokenStream $stream The declaration, as tokens
     * @param list<SqlToken> $tokens The same tokens, with nothing insignificant left in
     * @param int $opening Where the opening parenthesis is
     *
     * @return list<string> The names, or none where the parentheses never close
     */
    public function identifierList(SqlTokenStream $stream, array $tokens, int $opening): array
    {
        $depth = $tokens[$opening]->depth;
        $candidates = array_slice($tokens, $opening);
        array_shift($candidates);
        $identifiers = [];
        $index = $opening;
        foreach ($candidates as $token) {
            $index++;
            if ($token->depth === $depth) {
                if (self::isSymbol($token, ')')) {
                    return $identifiers;
                }

                return [];
            }
            if ($token->depth !== $depth + 1) {
                continue;
            }

            $identifier = $stream->identifierAt($index);
            if ($identifier !== null) {
                $identifiers[] = $identifier['name'];
            }
        }

        return [];
    }

    /**
     * Answers what a key says to do when the row it points at changes.
     *
     * A key that says nothing does nothing, which is what SQL means by NO
     * ACTION -- so a declaration with no ON clause answers the same as one
     * that spells it out.
     *
     * @param list<SqlToken> $tokens The declaration, as tokens
     * @param string $event The change the clause is about, DELETE or UPDATE
     *
     * @return ReferentialAction What to do to this row
     */
    public function action(array $tokens, string $event): ReferentialAction
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isTopLevel() || !$token->isKeyword('ON')) {
                continue;
            }
            $eventToken = $tokens[$index + 1] ?? null;
            if ($eventToken === null || !$eventToken->isKeyword($event)) {
                continue;
            }

            $action = $tokens[$index + 2] ?? null;
            if ($action === null) {
                return ReferentialAction::NoAction;
            }
            if ($action->isKeyword('CASCADE')) {
                return ReferentialAction::Cascade;
            }
            if ($action->isKeyword('RESTRICT')) {
                return ReferentialAction::Restrict;
            }
            if (!$action->isKeyword('SET')) {
                return ReferentialAction::NoAction;
            }

            $qualifier = $tokens[$index + 3] ?? null;
            if ($qualifier === null) {
                return ReferentialAction::NoAction;
            }
            if ($qualifier->isKeyword('NULL')) {
                return ReferentialAction::SetNull;
            }
            if ($qualifier->isKeyword('DEFAULT')) {
                return ReferentialAction::SetDefault;
            }

            return ReferentialAction::NoAction;
        }

        return ReferentialAction::NoAction;
    }

    /**
     * Answers where a keyword is written, at the level of the declaration itself.
     *
     * @param list<SqlToken> $tokens Tokens to search
     * @param string $keyword Keyword to look for
     *
     * @return int|null Where it is written, or null where it is not
     */
    public static function keywordIndex(array $tokens, string $keyword): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token->isTopLevel() && $token->isKeyword($keyword)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers where a symbol is written, at the level of the declaration itself.
     *
     * @param list<SqlToken> $tokens Tokens to search
     * @param string $symbol Symbol to look for
     * @param int $start Where to start looking
     *
     * @return int|null Where it is written, or null where it is not
     */
    public static function symbolIndex(array $tokens, string $symbol, int $start): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($index >= $start && $token->isTopLevel() && self::isSymbol($token, $symbol)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Reports whether a token is this symbol.
     *
     * @param SqlToken|null $token Token to test, or null past the end of what was written
     * @param string $symbol Symbol it must be
     *
     * @return bool True when it is
     */
    public static function isSymbol(?SqlToken $token, string $symbol): bool
    {
        if ($token === null) {
            return false;
        }

        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
