<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Internal built-in type spelling and numeric parameter groups.
 * @visibility SqlSemantics
 */
final class TypeWords
{
    /**
     * @param list<string> $words
     * @param list<NumericParameter> $parameters
     */
    public function __construct(public readonly array $words, public readonly array $parameters, public readonly bool $unsigned, public readonly ?string $characterSet, public readonly bool $binary)
    {
    }

    /**
     * Separates type identity from its sizes, signedness and encoding.
     */
    public static function read(Node $source): self
    {
        $words = [];
        $parameters = [];
        $depth = 0;
        $parameter = '';
        $charset = null;
        $unsigned = false;
        $binary = false;
        $skip = false;
        foreach ($source->tokens() as $token) {
            $word = strtoupper($token->text);
            if ($word === '(') {
                ++$depth;
            } elseif ($word === ')') {
                if ($depth === 1 && $parameter !== '') {
                    $parameters[] = new NumericParameter($parameter);
                    $parameter = '';
                }
                --$depth;
            } elseif ($depth > 0) {
                if (in_array($words[0] ?? '', ['ENUM', 'SET'], true)) {
                    continue;
                }
                if ($word === ',') {
                    $parameters[] = new NumericParameter($parameter);
                    $parameter = '';
                } else {
                    $parameter .= $token->text;
                }
            } elseif ($word === 'UNSIGNED' || $word === 'ZEROFILL') {
                $unsigned = true;
            } elseif ($word === 'SIGNED') {
                continue;
            } elseif (in_array($word, ['CHARACTER', 'CHARSET'], true) && $words !== [] && !in_array($words[0], ['NATIONAL'], true)) {
                $skip = true;
            } elseif ($skip) {
                if ($word !== 'SET') {
                    $charset = trim($token->text, "'`\"");
                }
            } elseif ($word === 'BINARY' && $words !== []) {
                $binary = true;
            } else {
                $words[] = $word;
            }
        }
        return new self($words, $parameters, $unsigned, $charset, $binary);
    }
}
