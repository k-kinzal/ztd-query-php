<?php

declare(strict_types=1);

use PhpFuzzer\Config;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenStream;

/** @var Config $config */
require_once __DIR__ . '/../vendor/autoload.php';

$profiles = [FakeSqlLexerProfiles::standard(), FakeSqlLexerProfiles::allCapabilities()];
$config->setMaxLen(4096);
$config->setTarget(static function (string $input) use ($profiles): void {
    foreach ($profiles as $profile) {
        $stream = SqlTokenStream::tokenize($input, $profile);
        $reconstructed = '';
        $offset = 0;
        foreach ($stream->tokens() as $token) {
            if ($token->text === '' || $token->offset !== $offset || $token->depth < 0 || $token->bracketDepth < 0) {
                throw new Error('Token stream positions violated for input ' . bin2hex($input) . ' on PHP ' . PHP_VERSION);
            }
            $reconstructed .= $token->text;
            $offset = $token->endOffset();
        }
        if ($reconstructed !== $input || $offset !== strlen($input)) {
            throw new Error('Token stream was not lossless for input ' . bin2hex($input) . ' on PHP ' . PHP_VERSION);
        }
        $replayed = SqlTokenStream::tokenize($reconstructed, $profile);
        $describe = static fn (SqlToken $token): array => [$token->kind->name, $token->text, $token->offset, $token->depth, $token->bracketDepth];
        if (array_map($describe, $stream->tokens()) !== array_map($describe, $replayed->tokens())) {
            throw new Error('Token stream replay changed for input ' . bin2hex($input) . ' on PHP ' . PHP_VERSION);
        }
    }
});
