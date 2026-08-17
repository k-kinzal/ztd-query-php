<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenDialect;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class ForeignKeyDefinitionParser
{
    /** @return array<string, ForeignKeyDefinition> */
    public function parseCreateTable(
        string $sql,
        SqlTokenDialect $dialect = SqlTokenDialect::Standard,
    ): array {
        $body = $this->tableBody($sql, $dialect);
        if ($body === null) {
            return [];
        }

        $foreignKeys = [];
        $index = 0;
        foreach (SqlTokenStream::tokenize($body, $dialect)->splitTopLevel() as $entry) {
            $stream = SqlTokenStream::tokenize($entry, $dialect);
            $first = $stream->identifierAt();
            $firstKeyword = $stream->firstTopLevelKeyword();
            $inlineColumn = $first !== null && !in_array(
                $firstKeyword,
                ['CONSTRAINT', 'FOREIGN', 'PRIMARY', 'UNIQUE', 'CHECK', 'EXCLUDE'],
                true,
            ) ? $first['name'] : null;
            $name = 'foreign_' . $index++;
            $definition = $this->parseEntry($stream, $name, $inlineColumn);
            if ($definition === null) {
                continue;
            }

            $foreignKeys[$definition['name']] = $definition['foreignKey'];
        }

        return $foreignKeys;
    }

    private function tableBody(string $sql, SqlTokenDialect $dialect): ?string
    {
        $tokens = SqlTokenStream::tokenize($sql, $dialect)->significantTokens();
        $afterTable = false;
        $opening = null;
        foreach ($tokens as $token) {
            if ($token->isTopLevel() && $token->isKeyword('TABLE')) {
                $afterTable = true;
                continue;
            }
            if ($afterTable && self::isSymbol($token, '(') && $token->isTopLevel()) {
                $opening = $token;
                break;
            }
        }
        if ($opening === null) {
            return null;
        }

        foreach ($tokens as $token) {
            if ($token->offset <= $opening->offset
                || !$token->isTopLevel()
                || !self::isSymbol($token, ')')
            ) {
                continue;
            }

            return substr($sql, $opening->endOffset(), $token->offset - $opening->endOffset());
        }

        return null;
    }

    /**
     * @return array{name: string, foreignKey: ForeignKeyDefinition}|null
     */
    private function parseEntry(
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
        if (($tokens[0] ?? null)?->isKeyword('CONSTRAINT') === true) {
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
     * @param list<SqlToken> $tokens
     * @return list<string>
     */
    private function foreignKeyColumns(
        SqlTokenStream $stream,
        array $tokens,
        int $references,
    ): array {
        $foreign = self::keywordIndex($tokens, 'FOREIGN');
        if ($foreign === null || ($tokens[$foreign + 1] ?? null)?->isKeyword('KEY') !== true) {
            return [];
        }

        $opening = self::symbolIndex($tokens, '(', $foreign + 2);

        return $opening !== null && $opening < $references
            ? $this->identifierList($stream, $tokens, $opening)
            : [];
    }

    /**
     * @param list<SqlToken> $tokens
     * @return array{table: string, columns: list<string>}|null
     */
    private function referencedRelation(
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
                break;
            }
            $table = $component['name'];
            $next = $component['next'];
        }

        $opening = self::symbolIndex($tokens, '(', $next);
        $columns = $opening !== null ? $this->identifierList($stream, $tokens, $opening) : [];

        return ['table' => $table, 'columns' => $columns];
    }

    /**
     * @param list<SqlToken> $tokens
     * @return list<string>
     */
    private function identifierList(SqlTokenStream $stream, array $tokens, int $opening): array
    {
        $closing = null;
        $depth = $tokens[$opening]->depth;
        foreach ($tokens as $index => $token) {
            if ($index <= $opening
                || $token->depth !== $depth
                || !self::isSymbol($token, ')')
            ) {
                continue;
            }
            $closing = $token;
            break;
        }
        if ($closing === null) {
            return [];
        }

        $openingToken = $tokens[$opening];
        $sql = '';
        foreach ($stream->tokens() as $token) {
            $sql .= $token->text;
        }
        $list = substr($sql, $openingToken->endOffset(), $closing->offset - $openingToken->endOffset());
        $identifiers = [];
        foreach (SqlTokenStream::tokenize($list)->splitTopLevel() as $part) {
            $identifier = SqlTokenStream::tokenize($part)->identifierAt();
            if ($identifier !== null) {
                $identifiers[] = $identifier['name'];
            }
        }

        return $identifiers;
    }

    /** @param list<SqlToken> $tokens */
    private function action(array $tokens, string $event): ReferentialAction
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isTopLevel()
                || !$token->isKeyword('ON')
                || ($tokens[$index + 1] ?? null)?->isKeyword($event) !== true
            ) {
                continue;
            }

            $first = strtoupper($tokens[$index + 2]->text ?? '');
            $second = strtoupper($tokens[$index + 3]->text ?? '');

            return match ([$first, $second]) {
                ['CASCADE', ''], ['CASCADE', 'ON'], ['CASCADE', 'DEFERRABLE'] => ReferentialAction::Cascade,
                ['RESTRICT', ''], ['RESTRICT', 'ON'], ['RESTRICT', 'DEFERRABLE'] => ReferentialAction::Restrict,
                ['SET', 'NULL'] => ReferentialAction::SetNull,
                ['SET', 'DEFAULT'] => ReferentialAction::SetDefault,
                ['NO', 'ACTION'] => ReferentialAction::NoAction,
                default => match ($first) {
                    'CASCADE' => ReferentialAction::Cascade,
                    'RESTRICT' => ReferentialAction::Restrict,
                    default => ReferentialAction::NoAction,
                },
            };
        }

        return ReferentialAction::NoAction;
    }

    /** @param list<SqlToken> $tokens */
    private static function keywordIndex(array $tokens, string $keyword): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token->isTopLevel() && $token->isKeyword($keyword)) {
                return $index;
            }
        }

        return null;
    }

    /** @param list<SqlToken> $tokens */
    private static function symbolIndex(array $tokens, string $symbol, int $start): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($index >= $start && $token->isTopLevel() && self::isSymbol($token, $symbol)) {
                return $index;
            }
        }

        return null;
    }

    private static function isSymbol(?SqlToken $token, string $symbol): bool
    {
        return $token !== null && $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
