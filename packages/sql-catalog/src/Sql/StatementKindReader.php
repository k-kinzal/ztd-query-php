<?php

declare(strict_types=1);

namespace SqlCatalog\Sql;

use SqlCatalog\Text\TextPattern;

/**
 * Reads what a statement does from the keyword it leads with.
 *
 * @visibility root
 */
final class StatementKindReader
{
    private const KEYWORDS = [
        'SELECT' => StatementKind::Select,
        'TABLE' => StatementKind::Select,
        'VALUES' => StatementKind::Select,
        'INSERT' => StatementKind::Insert,
        'UPDATE' => StatementKind::Update,
        'DELETE' => StatementKind::Delete,
        'REPLACE' => StatementKind::Replace,
        'UPSERT' => StatementKind::Replace,
        'MERGE' => StatementKind::Merge,
        'TRUNCATE' => StatementKind::Truncate,
        'CREATE' => StatementKind::Create,
        'ALTER' => StatementKind::Alter,
        'DROP' => StatementKind::Drop,
        'RENAME' => StatementKind::Alter,
        'CALL' => StatementKind::Call,
        'EXEC' => StatementKind::Call,
        'EXECUTE' => StatementKind::Call,
        'SHOW' => StatementKind::Show,
        'DESCRIBE' => StatementKind::Show,
        'DESC' => StatementKind::Show,
        'EXPLAIN' => StatementKind::Explain,
        'ANALYZE' => StatementKind::Explain,
        'BEGIN' => StatementKind::Transaction,
        'START' => StatementKind::Transaction,
        'COMMIT' => StatementKind::Transaction,
        'ROLLBACK' => StatementKind::Transaction,
        'SAVEPOINT' => StatementKind::Transaction,
        'SET' => StatementKind::Other,
        'USE' => StatementKind::Other,
        'PRAGMA' => StatementKind::Other,
        'GRANT' => StatementKind::Other,
        'REVOKE' => StatementKind::Other,
        'LOCK' => StatementKind::Other,
        'UNLOCK' => StatementKind::Other,
    ];

    private SqlLexer $lexer;

    /**
     * Builds a reader over the shared lexer.
     */
    public function __construct(?SqlLexer $lexer = null)
    {
        $this->lexer = $lexer ?? new SqlLexer();
    }

    /**
     * What the statement does, or `Unknown` when the leading keyword is missing or hidden by a gap.
     */
    public function read(TextPattern $pattern): StatementKind
    {
        $tokens = $this->lexer->tokenize($pattern->render(PlaceholderScanner::HOLE_MARKER));
        foreach ($tokens as $index => $token) {
            if ($token->kind === SqlTokenKind::Comment) {
                continue;
            }
            if ($token->kind !== SqlTokenKind::Word) {
                if ($token->kind === SqlTokenKind::Symbol && $token->text === '(') {
                    continue;
                }

                return StatementKind::Unknown;
            }
            if ($token->keyword() === 'WITH') {
                return $this->readAfterCommonTable(array_slice($tokens, $index + 1));
            }

            return self::KEYWORDS[$token->keyword()] ?? StatementKind::Unknown;
        }

        return StatementKind::Unknown;
    }

    /**
     * What the statement a `WITH` clause introduces does.
     *
     * @param list<SqlToken> $tokens
     */
    public function readAfterCommonTable(array $tokens): StatementKind
    {
        foreach ($tokens as $token) {
            if ($token->depth !== 0 || $token->kind !== SqlTokenKind::Word) {
                continue;
            }
            $kind = match ($token->keyword()) {
                'SELECT' => StatementKind::Select,
                'INSERT' => StatementKind::Insert,
                'UPDATE' => StatementKind::Update,
                'DELETE' => StatementKind::Delete,
                default => null,
            };
            if ($kind !== null) {
                return $kind;
            }
        }

        return StatementKind::Unknown;
    }
}
