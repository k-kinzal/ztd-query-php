<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Composition;

use SqlParser\Lexer\SourceException;
use SqlSemantics\Core\Ast\DialectParser;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\Traversal;
use SqlSemantics\Statement\Writer;

/**
 * Reads the SQL of a template statement into the values the release reads it as.
 *
 * A template writes a form with bare names, its slots, where operands go,
 * such as `SELECT slot0 IS NULL`. The release's own parser reads it, so the
 * form found in it is the one the server reads for that SQL in that release,
 * and a form the release lacks is a syntax error of the template. Each
 * template is read once; the values are immutable, so they are shared.
 *
 * @visibility SqlSemantics
 */
final class Templates
{
    /**
     * @var array<string, Element>
     */
    private array $commands = [];

    /**
     * Reads templates of the language.
     */
    public function __construct(private readonly Language $language)
    {
    }

    /**
     * Answers the command a template statement is read as.
     *
     * @throws CompositionException When the release does not read the template
     */
    public function command(string $sql): Element
    {
        if (!isset($this->commands[$sql])) {
            try {
                $tree = (new DialectParser($this->language))->parse($sql);
            } catch (SourceException $error) {
                throw new CompositionException('No form for ' . $sql . ' in ' . $this->language->version . ': ' . $error->getMessage(), 0, $error);
            }
            $this->commands[$sql] = $this->language->values()->statement($tree)->command;
        }

        return $this->commands[$sql];
    }

    /**
     * Answers the outermost value of a role in a command that writes the tokens of a text, or null.
     *
     * Neither whitespace nor letter case is compared, as the writer spells
     * keywords in capitals and lays tokens out its own way.
     *
     * @param class-string<Element> $role The role interface the value occupies
     */
    public function written(Element $command, string $text, string $role): ?Element
    {
        $wanted = strtolower(preg_replace('/\s+/', '', $text) ?? $text);
        foreach (Traversal::walk($command) as $value) {
            if ($value instanceof $role && strtolower(preg_replace('/\s+/', '', Writer::render($value)) ?? '') === $wanted) {
                return $value;
            }
        }

        return null;
    }
}
