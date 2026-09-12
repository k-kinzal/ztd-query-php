<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Value\Utf8;
use SqlFaker\Generation\Value\WordDomain;
use SqlFaker\MySql\Generation\Value\RadixDomain;

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
        $generator = new ValueLexemeGenerator('UNDERSCORE_CHARSET', new WordDomain(['_utf8mb4', '_utf8mb3', '_latin1', '_ascii', '_binary'], true), ['_utf8mb4', '_utf8mb3', '_latin1', '_ascii', '_binary'], 'charset', 'sql/sql_yacc.yy:literal:well-formed-charset');
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
        $hex = (new RadixDomain('0123456789abcdefABCDEF', '0x', ['X', 'x'], 1, 32))->digits($literal);
        if ($hex !== null) {
            return pack('H*', (strlen($hex) % 2 === 1 ? '0' : '') . $hex);
        }
        $bits = (new RadixDomain('01', '0b', ['B', 'b'], 1, 64))->digits($literal);
        if ($bits !== null) {
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
            '_ascii' => (new Utf8())->valid($bytes, 1),
            '_utf8mb3' => (new Utf8())->valid($bytes, 3),
            '_utf8mb4' => (new Utf8())->valid($bytes, 4),
            default => false,
        };
    }
}
