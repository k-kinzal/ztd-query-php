<?php

declare(strict_types=1);

namespace SqlFaker\Coverage\Verification;

use JsonException;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Coverage\CoverageSnapshotStore;

/**
 * Preserves first-witness input bytes and checked SQL independently of a fuzzer's mutable or minimized corpus.
 */
final class VerificationWitnessStore
{
    /**
     * Witness files are addressed by both the input and checked SQL hashes.
     */
    public function __construct(private readonly string $directory)
    {
    }

    /**
     * Stores complete reproduction material only when a new feature/verdict witness is recorded.
     * @throws CoverageException When the witness cannot be encoded or atomically saved
     */
    public function record(string $input, string $sql): void
    {
        $key = hash('sha256', hash('sha256', $input) . ':' . hash('sha256', $sql));
        try {
            $json = json_encode(['inputHex' => bin2hex($input), 'sql' => $sql], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new CoverageException('Cannot encode verification witness.', 0, $exception);
        }
        (new CoverageSnapshotStore($this->directory, $key))->write($json);
    }
}
