<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Parse;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Sqlite\Dialect\SqliteLexerProfile;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Where a reader has got to in an ON DUPLICATE KEY expression, and what the names in it mean.
 *
 * A grammar production has to say where it left off, and passing the position
 * back and forth by reference makes every production's signature about
 * bookkeeping rather than about grammar. Here the position is the thing being
 * read, so a production takes one argument and moves it along.
 *
 * The table's own name and the alias the incoming row was given are carried
 * here too, because deciding whether `t.qty` means the row that is there or
 * the row being written is the same question at every depth of the expression.
 */
final class SqliteUpsertExpressionCursor
{
    /** @var int How many tokens have been read */
    private int $index = 0;

    /**
     * @param string $sql Expression being read, for anything it refuses
     * @param string $tableName Table the statement writes to
     * @param string|null $incomingAlias Name the statement gave the incoming row, or null to use EXCLUDED
     * @param list<SqlToken> $tokens The expression, with nothing insignificant left in
     * @param SqliteUpsertLiteral $literals Reads a literal or a name back out of how it was written
     */
    public function __construct(
        private readonly string $sql,
        private readonly string $tableName,
        private readonly ?string $incomingAlias,
        private readonly array $tokens,
        private readonly SqliteUpsertLiteral $literals = new SqliteUpsertLiteral(),
    ) {
    }

    /**
     * Reads an expression into a cursor over it.
     *
     * @param string $sql Expression to read
     * @param string $tableName Table the statement writes to
     * @param string|null $incomingAlias Name the statement gave the incoming row
     *
     * @return self A cursor at the start of the expression
     */
    public static function over(string $sql, string $tableName, ?string $incomingAlias = null): self
    {
        return new self(
            $sql,
            $tableName,
            $incomingAlias,
            SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens(),
        );
    }

    /**
     * Answers the token the cursor is on.
     *
     * @return SqlToken|null The token, or null past the end of the expression
     */
    public function token(): ?SqlToken
    {
        return $this->tokens[$this->index] ?? null;
    }

    /**
     * Answers a token further along than the one the cursor is on.
     *
     * @param int $offset How far along to look
     *
     * @return SqlToken|null The token, or null past the end of the expression
     */
    public function tokenAt(int $offset): ?SqlToken
    {
        return $this->tokens[$this->index + $offset] ?? null;
    }

    /**
     * Moves the cursor along.
     *
     * @param int $by How many tokens to move past
     */
    public function advance(int $by = 1): void
    {
        $this->index += $by;
    }

    /**
     * Reports whether the whole expression has been read.
     *
     * @return bool True when nothing is left
     */
    public function atEnd(): bool
    {
        return $this->index >= count($this->tokens);
    }

    /**
     * Reports whether the cursor is on this keyword.
     *
     * @param string $keyword Keyword to test for
     *
     * @return bool True when it is
     */
    public function isKeyword(string $keyword): bool
    {
        return $this->token()?->isKeyword($keyword) === true;
    }

    /**
     * Reports whether the cursor is on one of these symbols.
     *
     * @param list<string> $symbols Symbols to test for
     *
     * @return bool True when it is
     */
    public function isSymbol(array $symbols): bool
    {
        $token = $this->token();

        return $token !== null && $this->literals->isSymbol($token, $symbols);
    }

    /**
     * Reports whether a token further along is one of these symbols.
     *
     * @param int $offset How far along to look
     * @param list<string> $symbols Symbols to test for
     *
     * @return bool True when it is
     */
    public function isSymbolAt(int $offset, array $symbols): bool
    {
        $token = $this->tokenAt($offset);

        return $token !== null && $this->literals->isSymbol($token, $symbols);
    }

    /**
     * Reports whether the cursor is on a name.
     *
     * @return bool True for a bare word or a quoted name
     */
    public function isName(): bool
    {
        $token = $this->token();

        return $token !== null && $this->literals->isName($token);
    }

    /**
     * Reads the name the cursor is on, and moves past it.
     *
     * @return string The name, with the quoting taken off
     *
     * @throws UnsupportedSqlException When the cursor is not on a name
     */
    public function takeName(): string
    {
        $token = $this->token();
        if ($token === null || !$this->literals->isName($token)) {
            throw $this->unsupported();
        }
        $this->advance();

        return $this->literals->nameOf($token);
    }

    /**
     * Answers which row a qualified name is about.
     *
     * @param string $qualifier Name written before the dot
     *
     * @return UpsertColumnSource The row that name stands for
     *
     * @throws UnsupportedSqlException When it stands for neither row
     */
    public function sourceOf(string $qualifier): UpsertColumnSource
    {
        if (strcasecmp($qualifier, $this->incomingAlias ?? 'EXCLUDED') === 0) {
            return UpsertColumnSource::Incoming;
        }
        if (strcasecmp($qualifier, $this->tableName) === 0) {
            return UpsertColumnSource::Existing;
        }

        throw $this->unsupported();
    }

    /**
     * Answers the refusal for an expression ZTD cannot read.
     *
     * @return UnsupportedSqlException The refusal, naming the expression
     */
    public function unsupported(): UnsupportedSqlException
    {
        return new UnsupportedSqlException($this->sql, 'Unsupported UPSERT expression');
    }
}
