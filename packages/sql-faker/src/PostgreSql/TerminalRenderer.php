<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Contract\RandomSource;
use SqlFaker\Contract\TerminalRenderer as TerminalRendererContract;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Grammar\RandomStringGenerator;

final class TerminalRenderer implements TerminalRendererContract
{
    private RandomStringGenerator $rsg;
    private int $identifierOrdinal = 0;

    public function __construct(
        private readonly RandomSource $random,
        private readonly LexicalValueSource $lexicalValues,
    ) {
        $this->rsg = new RandomStringGenerator($random);
    }

    public function render(TerminalSequence $terminals): TokenSequence
    {
        $this->identifierOrdinal = 0;

        $tokens = [];
        foreach ($terminals->terminals as $name) {
            $token = match ($name) {
                'MODE_TYPE_NAME', 'MODE_PLPGSQL_EXPR', 'MODE_PLPGSQL_ASSIGN1',
                'MODE_PLPGSQL_ASSIGN2', 'MODE_PLPGSQL_ASSIGN3' => null,
                'TYPECAST' => '::',
                'DOT_DOT' => '..',
                'COLON_EQUALS' => ':=',
                'EQUALS_GREATER' => '=>',
                'NOT_EQUALS' => '!=',
                'LESS_EQUALS' => '<=',
                'GREATER_EQUALS' => '>=',
                'NOT_LA' => 'NOT',
                'WITH_LA' => 'WITH',
                'WITHOUT_LA' => 'WITHOUT',
                'FORMAT_LA' => 'FORMAT',
                'NULLS_LA' => 'NULLS',
                'IDENT' => $this->nextCanonicalIdentifier(),
                'UIDENT' => sprintf('U&"%s"', $this->nextCanonicalIdentifier()),
                'SCONST' => $this->lexicalValues->stringLiteral(),
                'DO_BODY_SCONST' => $this->lexicalValues->doBodyLiteral(),
                'USCONST' => sprintf("U&'%s'", $this->rsg->mixedAlnumString(1, 24)),
                'ICONST' => $this->lexicalValues->integerLiteral(),
                'FCONST' => $this->lexicalValues->decimalLiteral(),
                'BCONST' => $this->lexicalValues->binaryLiteral(),
                'XCONST' => $this->lexicalValues->hexLiteral(),
                'Op' => $this->generateOperator(),
                'PARAM' => $this->lexicalValues->parameterMarker(),
                default => str_ends_with($name, '_P') ? substr($name, 0, -2) : $name,
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

    private function generateOperator(): string
    {
        /** @var string $operator */
        $operator = $this->random->stringElement(['+', '-', '*', '/', '<', '>', '=', '~', '!', '@', '#', '%', '^', '&', '|']);

        return $operator;
    }
}
