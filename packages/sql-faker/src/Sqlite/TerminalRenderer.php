<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\TerminalRenderer as TerminalRendererContract;
use SqlFaker\Contract\TerminalSequence;
use SqlFaker\Contract\TokenSequence;
use SqlFaker\Grammar\RandomStringGenerator;

final class TerminalRenderer implements TerminalRendererContract
{
    /**
     * @var array<string, true>
     */
    private const SQLITE_RESERVED_WORDS = [
        'add' => true, 'all' => true, 'alter' => true, 'and' => true, 'as' => true,
        'between' => true, 'by' => true, 'case' => true, 'check' => true,
        'collate' => true, 'commit' => true, 'create' => true, 'default' => true,
        'delete' => true, 'distinct' => true, 'do' => true, 'drop' => true,
        'else' => true, 'end' => true, 'escape' => true, 'except' => true,
        'exists' => true, 'for' => true, 'foreign' => true, 'from' => true,
        'group' => true, 'having' => true, 'if' => true, 'in' => true,
        'index' => true, 'insert' => true, 'into' => true, 'is' => true,
        'join' => true, 'key' => true, 'limit' => true, 'match' => true,
        'no' => true, 'not' => true, 'null' => true, 'of' => true,
        'on' => true, 'or' => true, 'order' => true, 'primary' => true,
        'references' => true, 'select' => true, 'set' => true, 'table' => true,
        'then' => true, 'to' => true, 'union' => true, 'unique' => true,
        'update' => true, 'using' => true, 'values' => true, 'when' => true,
        'where' => true, 'with' => true,
    ];

    private RandomStringGenerator $rsg;
    private int $identifierOrdinal = 0;

    public function __construct(
        private readonly FakerGenerator $faker,
        private readonly LexicalValueSource $lexicalValues,
    ) {
        $this->rsg = new RandomStringGenerator($faker);
    }

    public function render(TerminalSequence $terminals): TokenSequence
    {
        $this->identifierOrdinal = 0;

        $tokens = [];
        foreach ($terminals->terminals as $name) {
            $tokens[] = match ($name) {
                'ID', 'id', 'idj' => $this->generateSqliteIdentifier(),
                'ids' => $this->lexicalValues->quotedIdentifier(),
                'STRING' => $this->lexicalValues->stringLiteral(),
                'number', 'INTEGER', 'QNUMBER' => $this->lexicalValues->integerLiteral(),
                'VARIABLE' => '?' . $this->rsg->parameterIndex(),
                'LP' => '(',
                'RP' => ')',
                'SEMI' => ';',
                'COMMA' => ',',
                'DOT' => '.',
                'EQ' => '=',
                'LT' => '<',
                'PLUS' => '+',
                'MINUS' => '-',
                'STAR' => '*',
                'BITAND' => '&',
                'BITNOT' => '~',
                'CONCAT' => '||',
                'PTR' => '->',
                'JOIN_KW' => $this->generateJoinKeyword(),
                'CTIME_KW' => $this->generateCtimeKeyword(),
                'LIKE_KW' => $this->generateLikeKeyword(),
                'AUTOINCR' => 'AUTOINCREMENT',
                'COLUMNKW' => 'COLUMN',
                default => $name,
            };
        }

        return new TokenSequence($tokens);
    }

    private function generateSqliteIdentifier(): string
    {
        $identifier = $this->rsg->canonicalIdentifier($this->identifierOrdinal++);

        if (isset(self::SQLITE_RESERVED_WORDS[strtolower($identifier)])) {
            return '"' . $identifier . '"';
        }

        return $identifier;
    }

    private function generateJoinKeyword(): string
    {
        /** @var string $keyword */
        $keyword = $this->faker->randomElement(['LEFT', 'RIGHT', 'INNER', 'CROSS', 'NATURAL LEFT', 'NATURAL INNER', 'NATURAL CROSS']);

        return $keyword;
    }

    private function generateCtimeKeyword(): string
    {
        /** @var string $keyword */
        $keyword = $this->faker->randomElement(['CURRENT_TIME', 'CURRENT_DATE', 'CURRENT_TIMESTAMP']);

        return $keyword;
    }

    private function generateLikeKeyword(): string
    {
        /** @var string $keyword */
        $keyword = $this->faker->randomElement(['LIKE', 'GLOB']);

        return $keyword;
    }
}
