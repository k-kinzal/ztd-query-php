<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\TerminalRenderer as TerminalRendererContract;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Grammar\RandomStringGenerator;

final class TerminalRenderer implements TerminalRendererContract
{
    private RandomStringGenerator $rsg;
    private int $identifierOrdinal = 0;

    public function __construct(
        FakerGenerator $faker,
        private readonly LexicalValueSource $lexicalValues,
    ) {
        $this->rsg = new RandomStringGenerator($faker);
    }

    public function render(TerminalSequence $terminals): TokenSequence
    {
        $this->identifierOrdinal = 0;

        $tokens = [];
        foreach ($terminals->terminals as $name) {
            $token = match ($name) {
                'END_OF_INPUT' => null,
                'EQ' => '=',
                'EQUAL_SYM' => '<=>',
                'LT' => '<',
                'GT_SYM' => '>',
                'LE' => '<=',
                'GE' => '>=',
                'NE' => '<>',
                'SHIFT_LEFT' => '<<',
                'SHIFT_RIGHT' => '>>',
                'AND_AND_SYM' => '&&',
                'OR2_SYM', 'OR_OR_SYM' => '||',
                'NOT2_SYM' => 'NOT',
                'SET_VAR' => ':=',
                'JSON_SEPARATOR_SYM' => '->',
                'JSON_UNQUOTED_SEPARATOR_SYM' => '->>',
                'NEG' => '-',
                'PARAM_MARKER' => '?',
                'IDENT' => $this->nextCanonicalIdentifier(),
                'IDENT_QUOTED' => '`' . $this->nextCanonicalIdentifier() . '`',
                'TEXT_STRING' => $this->lexicalValues->stringLiteral(),
                'NCHAR_STRING' => $this->lexicalValues->nationalStringLiteral(),
                'DOLLAR_QUOTED_STRING_SYM' => $this->lexicalValues->dollarQuotedString(),
                'NUM' => $this->lexicalValues->integerLiteral(),
                'LONG_NUM' => $this->lexicalValues->longIntegerLiteral(),
                'ULONGLONG_NUM' => $this->lexicalValues->unsignedBigIntLiteral(),
                'DECIMAL_NUM' => $this->lexicalValues->decimalLiteral(),
                'FLOAT_NUM' => $this->lexicalValues->floatLiteral(),
                'HEX_NUM' => $this->lexicalValues->hexLiteral(),
                'BIN_NUM' => $this->lexicalValues->binaryLiteral(),
                'LEX_HOSTNAME' => $this->lexicalValues->hostname(),
                'FILTER_DB_TABLE_PATTERN' => $this->lexicalValues->filterWildcardPattern(),
                'RESET_MASTER_INDEX' => $this->lexicalValues->resetMasterIndex(),
                'WITH_ROLLUP_SYM' => 'WITH ROLLUP',
                default => str_ends_with($name, '_SYM') ? substr($name, 0, -4) : $name,
            };

            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        return new TokenSequence($tokens);
    }

    private function nextCanonicalIdentifier(): string
    {
        return $this->rsg->canonicalIdentifier($this->identifierOrdinal++);
    }
}
