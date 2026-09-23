<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

/**
 * Internal built-in type spelling and classified parameter groups.
 * @visibility SqlSemantics
 */
final class TypeWords
{
    /**
     * @param list<string> $words
     * @param list<NumericParameter|\SqlSemantics\Type\Modifier\TextParameter|\SqlSemantics\Type\Modifier\IdentifierParameter|\SqlSemantics\Type\Modifier\NegatedParameter> $parameters
     */
    public function __construct(public readonly array $words, public readonly array $parameters, public readonly bool $unsigned, public readonly ?string $characterSet, public readonly bool $binary, public readonly bool $national = false)
    {
    }

    /**
     * Separates type identity from its sizes, signedness and encoding.
     */
    public static function read(Node $source, Dialect $dialect): self
    {
        if ($dialect === Dialect::MySql) {
            return MySql\DeclarationWords::read($source);
        }
        $words = [];
        $parameters = $dialect === Dialect::PostgreSql ? ModifierBinder::parameters($source) : [];
        $parameters = $parameters === [] ? TypeTokens::numbers($source) : $parameters;
        $charset = null;
        $unsigned = false;
        $binary = false;
        $skip = false;
        foreach (TypeTokens::outer($source) as $token) {
            $word = strtoupper($token->text);
            if ($word === 'UNSIGNED' || $word === 'ZEROFILL') {
                $unsigned = true;
            } elseif ($word === 'SIGNED') {
                continue;
            } elseif (in_array($word, ['CHARACTER', 'CHARSET'], true) && $words !== [] && !in_array($words[0], ['NATIONAL'], true)) {
                $skip = true;
            } elseif ($skip) {
                if ($word !== 'SET') {
                    $charset = trim($token->text, "'`\"");
                    $skip = false;
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
