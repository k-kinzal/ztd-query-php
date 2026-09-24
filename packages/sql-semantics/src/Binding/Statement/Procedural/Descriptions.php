<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\Schema\DescribeTableStatement;
use SqlSemantics\Model\Statement\Origin;

/**
 * Binds the table form of DESCRIBE, DESC and EXPLAIN; explaining a statement belongs to the plan binder.
 * @visibility SqlSemantics
 */
final class Descriptions
{
    /**
     * Returns null for the statement-explaining form of the same grammar rule.
     */
    public static function bind(Origin $origin, Node $node, QueryContext $context): ?DescribeTableStatement
    {
        if (Tree::child($node, ['table_ident']) === null) {
            return null;
        }
        $table = TableOccurrence::resolve($node, $context, $origin->scopeId);
        if (!$table instanceof TableReference) {
            Tree::invalid($node, 'described table');
        }
        $column = Tree::child($node, ['opt_describe_column']);
        $token = $column === null ? null : ($column->tokens()[0] ?? null);
        return new DescribeTableStatement($origin, $table, $token === null ? null : self::pattern($token, $context->tables->identifiers));
    }

    /**
     * Decodes a column identifier, string, hexadecimal or bit literal to the pattern bytes the server matches.
     */
    public static function pattern(Token $token, Identifiers $identifiers): string
    {
        $text = $token->text;
        $prefix = strtolower(substr($text, 0, 2));
        return match (true) {
            $prefix === "x'" => (string) hex2bin(substr($text, 2, -1)),
            $prefix === '0x' => (string) hex2bin(str_pad(substr($text, 2), (strlen($text) - 1) & ~1, '0', STR_PAD_LEFT)),
            $prefix === "b'" => self::bits(substr($text, 2, -1)),
            $prefix === '0b' => self::bits(substr($text, 2)),
            default => MySqlNames::read($token, $identifiers),
        };
    }

    /**
     * Packs binary digits into bytes, padding the most significant byte with zero bits.
     */
    public static function bits(string $digits): string
    {
        $bytes = '';
        foreach (str_split(str_pad($digits, (int) ceil(strlen($digits) / 8) * 8, '0', STR_PAD_LEFT), 8) as $byte) {
            $bytes .= $byte === '' ? '' : chr((int) bindec($byte));
        }
        return $bytes;
    }
}
