<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;

/**
 * sql_yacc.yy literal actions require introduced bytes to be well formed in the selected character set.
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_yacc.yy#L13461-L13522
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy#L14780-L14807
 */
final class CharsetLexemeGenerator implements LexemeGenerator
{
    /**
     * Filters a finite declared charset domain against the already resolved right-hand literal.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $generator = new PatternLexemeGenerator('UNDERSCORE_CHARSET', '/\A_(?:utf8mb4|utf8mb3|latin1|ascii|binary)\z/Di', ['_utf8mb4', '_utf8mb3', '_latin1', '_ascii', '_binary'], 'charset', 'sql/sql_yacc.yy:literal:well-formed-charset');
        $candidates = $generator->generate($input);
        if ($candidates === null) {
            return null;
        }
        $bytes = $this->bytes($input->right->parts[0]->lexeme->text ?? '');
        $valid = [];
        foreach ($candidates->sequences() as $candidate) {
            if ($this->accepts($candidate->lexemes[0]->text, $bytes)) {
                $valid[] = $candidate;
            }
        }
        return LexemeCandidates::of(...$valid);
    }

    /**
     * Decodes complete binary literal forms; text-literal quoting consists only of ASCII bytes.
     */
    public function bytes(string $literal): string
    {
        if (preg_match("/\\A(?:0x([0-9a-fA-F]+)|[xX]'([0-9a-fA-F]*)')\\z/D", $literal, $match) === 1) {
            $hex = $match[1] !== '' ? $match[1] : $match[2];
            return pack('H*', (strlen($hex) % 2 === 1 ? '0' : '') . $hex);
        }
        if (preg_match("/\\A(?:0b([01]+)|[bB]'([01]*)')\\z/D", $literal, $match) === 1) {
            $bits = $match[1] !== '' ? $match[1] : $match[2];
            if ($bits === '') {
                return '';
            }
            $bits = str_pad($bits, (int) (ceil(strlen($bits) / 8) * 8), '0', STR_PAD_LEFT);
            return implode('', array_map(static fn (string $byte): string => chr(intval($byte, 2)), str_split($bits, 8)));
        }
        return $literal;
    }

    /**
     * latin1 and binary accept every byte; ASCII and the two UTF-8 widths retain their own contracts.
     */
    public function accepts(string $charset, string $bytes): bool
    {
        return match (strtolower($charset)) {
            '_binary', '_latin1' => true,
            '_ascii' => preg_match('/[\x80-\xff]/', $bytes) !== 1,
            '_utf8mb3' => preg_match('//u', $bytes) === 1 && preg_match('/[\xf0-\xf4]/', $bytes) !== 1,
            '_utf8mb4' => preg_match('//u', $bytes) === 1,
            default => false,
        };
    }
}
