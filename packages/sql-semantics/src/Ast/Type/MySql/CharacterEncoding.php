<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type\MySql;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;

/**
 * Reads character-set selection separately from type names and length operands.
 * @visibility SqlSemantics
 */
final class CharacterEncoding
{
    /**
     * An absent name uses the applicable default character set.
     */
    public function __construct(public readonly ?string $name, public readonly bool $binary)
    {
    }

    /**
     * Decodes explicit names and the ASCII, UNICODE, and BYTE character-set aliases.
     */
    public static function read(Node $source): self
    {
        $options = Tree::outer($source, ['opt_binary', 'opt_charset_with_opt_binary', 'opt_bin_mod']);
        $name = null;
        $binary = false;
        foreach ($options as $option) {
            $charset = Tree::outer($option, ['charset_name'])[0] ?? null;
            $charsetTokens = $charset?->tokens() ?? [];
            if ($charset !== null) {
                $name = MySqlNames::read($charsetTokens[0], new Identifiers(Dialect::MySql));
            }
            foreach ($option->tokens() as $token) {
                if (in_array($token, $charsetTokens, true)) {
                    continue;
                }
                $name = match ($token->name) {
                    'ASCII_SYM' => 'latin1', 'UNICODE_SYM' => 'ucs2', 'BYTE_SYM' => 'binary', default => $name,
                };
                $binary = $binary || in_array($token->name, ['BINARY_SYM', 'BINARY'], true);
            }
        }
        return new self($name, $binary);
    }
}
