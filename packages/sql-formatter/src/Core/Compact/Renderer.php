<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

use SqlFormatter\Core\FormattingException;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;

/**
 * Renders and verifies compact SQL using a dialect-specific canonical token stream.
 *
 * @visibility SqlFormatter
 */
final class Renderer
{
    /**
     * @var array<string, string> Lexical decisions reused while testing reductions
     */
    private array $separators = [];
    private readonly Spacing $spacing;

    /**
     * Shares the caller's parser for lexical decisions and output verification.
     */
    public function __construct(private readonly SqlParser $parser, private readonly Settings $settings)
    {
        $this->spacing = new Spacing($parser, $settings);
    }

    /**
     * Removes optional syntax, then reparses to check the canonical form.
     *
     * @throws FormattingException When output verification fails
     */
    public function render(Node $tree): string
    {
        $this->separators = [];
        $document = $this->document($tree);
        $tokens = $document->tokens;
        $result = $this->write($tokens, $document->trailing);
        try {
            $formatted = $this->document($this->parser->parse($result));
        } catch (SourceException $exception) {
            throw new FormattingException('Compact SQL could not be parsed with the original grammar.', 0, $exception);
        }
        if ($document->signature() !== $formatted->signature()) {
            throw new FormattingException('Compaction changed the canonical SQL token stream.');
        }
        foreach ($document->candidates as $offsets) {
            $candidate = array_values(array_filter($tokens, static fn (Token $token): bool => !in_array($token->offset, $offsets, true)));
            $sql = $this->write($candidate, $document->trailing);
            try {
                $reparsed = $this->document($this->parser->parse($sql));
            } catch (SourceException) {
                continue;
            }
            if ($document->signature() === $reparsed->signature()) {
                $tokens = $candidate;
                $result = $sql;
            }
        }
        return $result;
    }

    /**
     * Emits a canonical stream with only lexically necessary separators.
     *
     * @param list<Token> $tokens
     */
    public function write(array $tokens, string $trailing): string
    {
        $result = '';
        foreach ($tokens as $index => $token) {
            $previous = $tokens[$index - 1] ?? null;
            $separator = '';
            if ($previous !== null && $token->leading === '') {
                $next = $tokens[$index + 1] ?? null;
                $before = $tokens[$index - 2] ?? null;
                $key = serialize([$previous->name, $previous->text, $token->name, $token->text, $next?->text, $before?->text]);
                $separator = $this->separators[$key] ??= $this->spacing->between($previous, $token, $next, $before);
            }
            $result .= $token->leading . $separator . $token->text;
        }
        return $result . $trailing;
    }

    /**
     * Selects dialect-specific normalization without relying on a default release.
     */
    public function document(Node $tree): Document
    {
        return new Document($tree, $this->settings);
    }
}
