<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Procedural;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\FieldLayout;
use SqlSemantics\Model\Statement\Loading\LineLayout;
use SqlSemantics\Model\Statement\Loading\LoadLayout;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Reads the input layout and the numeric options of LOAD DATA and LOAD XML; repeated separators keep the last one, as the server merges them.
 * @visibility SqlSemantics
 */
final class LoadClauses
{
    /**
     * Reads the character set, FIELDS, LINES or ROWS IDENTIFIED BY, and IGNORE n LINES; an XML row tag is the line terminator.
     * @throws InvalidSql
     */
    public static function layout(Node $node, QueryContext $context): LoadLayout
    {
        $separators = ['TERMINATED' => null, 'ENCLOSED' => null, 'ESCAPED' => null, 'LINES' => null, 'STARTING' => null];
        $rows = Tree::child($node, ['opt_xml_rows_identified_by']);
        if ($rows !== null && Tree::text($rows) !== '') {
            $separators['LINES'] = self::literal($rows);
        }
        $optional = false;
        foreach (Tree::outer($node, ['field_term', 'line_term']) as $term) {
            $word = strtoupper($term->tokens()[0]->text ?? '');
            $optional = $optional || $word === 'OPTIONALLY';
            $key = match (true) {
                $term->name === 'line_term' && $word === 'TERMINATED' => 'LINES',
                $word === 'OPTIONALLY' => 'ENCLOSED',
                default => $word,
            };
            $separators[$key] = self::literal($term);
        }
        $ignored = Tree::child($node, ['opt_ignore_lines']);
        try {
            return new LoadLayout(self::characterSet($node, $context), new FieldLayout($separators['TERMINATED'], $separators['ENCLOSED'], $optional, $separators['ESCAPED']), new LineLayout($separators['LINES'], $separators['STARTING']), $ignored === null || Tree::text($ignored) === '' ? 0 : (int) ($ignored->tokens()[1]->text ?? '0'));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::LoadOption, $node, $error);
        }
    }

    /**
     * Reads the input character set; DEFAULT, accepted before MySQL 8.0, means no character set clause.
     */
    public static function characterSet(Node $node, QueryContext $context): ?string
    {
        $clause = Tree::child($node, ['opt_load_data_charset']);
        $tokens = $clause === null ? [] : $clause->tokens();
        $name = $tokens[count($tokens) - 1] ?? null;
        if ($name === null || in_array(strtoupper($name->text), ['DEFAULT'], true)) {
            return null;
        }
        return strtoupper($name->text) === 'BINARY' && $name->name !== 'TEXT_STRING' && !str_starts_with($name->text, '`') ? 'binary' : MySqlNames::read($name, $context->tables->identifiers);
    }

    /**
     * Binds the separator literal that ends a clause.
     * @throws InvalidSql
     */
    public static function literal(Node $node): Literal
    {
        $tokens = $node->tokens();
        return self::token($tokens[count($tokens) - 1] ?? Tree::invalid($node, 'load separator'), $node);
    }

    /**
     * Binds one string, hexadecimal or bit token as a literal.
     * @throws InvalidSql
     */
    public static function token(Token $token, Node $node): Literal
    {
        $literal = (new LiteralBinder(Dialect::MySql))->bind($token);
        return $literal instanceof Literal ? $literal : throw new InvalidSql(InputViolation::LoadOption, $node);
    }

    /**
     * Reads COUNT n; another word or zero files is a syntax error on the server.
     * @throws InvalidSql
     */
    public static function fileCount(Node $node): ?int
    {
        $clause = Tree::child($node, ['opt_source_count']);
        $tokens = $clause === null ? [] : $clause->tokens();
        if ($tokens === []) {
            return null;
        }
        $count = (int) ($tokens[1]->text ?? '0');
        if (strtoupper($tokens[0]->text) !== 'COUNT' || $count < 1) {
            throw new InvalidSql(InputViolation::LoadOption, $clause);
        }
        return $count;
    }

    /**
     * Reads the integer of an optional `KEYWORD = n` clause.
     */
    public static function number(Node $node, string $rule): ?int
    {
        $clause = Tree::child($node, [$rule]);
        $tokens = $clause === null ? [] : $clause->tokens();
        return $tokens === [] ? null : (int) $tokens[count($tokens) - 1]->text;
    }

    /**
     * Reads MEMORY = size as decimal bytes: an unsigned integer, or digits with a K, M or G suffix below 2^31.
     * @throws InvalidSql
     */
    public static function memory(Node $node): ?string
    {
        $clause = Tree::child($node, ['opt_load_memory']);
        $tokens = $clause === null ? [] : $clause->tokens();
        $size = $tokens[count($tokens) - 1] ?? null;
        if ($clause === null || $size === null) {
            return null;
        }
        $text = $size->text;
        if (preg_match('/^0[xX]([0-9a-fA-F]+)$/D', $text, $hex) === 1) {
            return self::decimal($hex[1]);
        }
        if (preg_match('/^[0-9]+$/D', $text) === 1) {
            $digits = ltrim($text, '0');
            return $digits === '' ? '0' : $digits;
        }
        if (preg_match('/^([0-9]+)([kKmMgG])$/D', $text, $parts) !== 1 || strlen(ltrim($parts[1], '0')) > 10 || (int) $parts[1] >= 2 ** 31) {
            throw new InvalidSql(InputViolation::LoadOption, $clause);
        }
        $shift = match (strtoupper($parts[2])) {
            'K' => 10,
            'M' => 20,
            'G' => 30,
        };
        return (string) ((int) $parts[1] << $shift);
    }

    /**
     * Converts hexadecimal digits to decimal digits without a width limit.
     */
    public static function decimal(string $hex): string
    {
        $digits = [0];
        foreach (str_split(strtolower($hex)) as $character) {
            $carry = (int) hexdec($character);
            foreach ($digits as $index => $digit) {
                $value = $digit * 16 + $carry;
                $digits[$index] = $value % 10;
                $carry = intdiv($value, 10);
            }
            while ($carry > 0) {
                $digits[] = $carry % 10;
                $carry = intdiv($carry, 10);
            }
        }
        $decimal = ltrim(implode('', array_reverse($digits)), '0');
        return $decimal === '' ? '0' : $decimal;
    }
}
