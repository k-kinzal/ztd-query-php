<?php

declare(strict_types=1);

namespace SqlCatalog\Platform\Sqlite;

use SqlCatalog\Core\Reporter\SqlFormatter as Contract;
use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Formatter;
use SqlFormatter\Core\FormattingException;
use SqlFormatter\Core\Style;
use SqlFormatter\Platform\Sqlite\Dialect;
use SqlParser\Lexer\SourceException;
use SqlParser\Sqlite\SqliteParser;

/**
 * Formats Sqlite SQL for catalog reports.
 *
 * @visibility root
 */
final class SqlFormatter implements Contract
{
    private ?Formatter $formatter = null;

    /**
     * Formats accepted syntax and leaves unsupported input to another policy.
     */
    public function format(string $sql): ?string
    {
        $this->formatter ??= new Formatter(new SqliteParser(), new Dialect(), new FormatOptions(style: Style::Expanded));
        try {
            return $this->formatter->format($sql);
        } catch (SourceException|FormattingException) {
            return null;
        }
    }
}
