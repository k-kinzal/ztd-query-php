<?php

declare(strict_types=1);

namespace SqlFaker\Coverage\Verification;

use SqlFaker\Coverage\CoverageException;

/**
 * The first observed witness for one feature and verdict, with hashes of the exact checked input and SQL.
 */
final class VerificationEvent
{
    /**
     * @param 'accepted'|'semantic-inconclusive'|'unsupported'|'finding'|'infrastructure-failure' $status
     * @param 'production'|'lexeme'|'compound'|'version-case'|'rewrite'|'spacing' $kind
     */
    public function __construct(
        public readonly string $status,
        public readonly string $kind,
        public readonly string $id,
        public readonly string $inputHash,
        public readonly string $sqlHash,
        public readonly string $code,
    ) {
    }

    /**
     * Separates verdicts and feature namespaces even when their textual identifiers coincide.
     */
    public function key(): string
    {
        return hash('sha256', serialize([$this->status, $this->kind, $this->id]));
    }

    /**
     * @return array{status: string, kind: string, id: string, inputHash: string, sqlHash: string, code: string}
     */
    public function toArray(): array
    {
        return ['status' => $this->status, 'kind' => $this->kind, 'id' => $this->id,
            'inputHash' => $this->inputHash, 'sqlHash' => $this->sqlHash, 'code' => $this->code];
    }

    /**
     * Reconstructs only validated witnesses from persistent data.
     * @throws CoverageException When any witness field is malformed
     */
    public static function fromArray(mixed $row): self
    {
        if (!is_array($row)) {
            throw new CoverageException('Invalid verification witness.');
        }
        $fields = [];
        foreach (['status', 'kind', 'inputHash', 'sqlHash', 'id', 'code'] as $name) {
            $value = $row[$name] ?? null;
            if (!is_string($value)) {
                throw new CoverageException('Invalid verification witness field: ' . $name);
            }
            $fields[$name] = $value;
        }
        $status = $fields['status'];
        $kind = $fields['kind'];
        $inputHash = $fields['inputHash'];
        $sqlHash = $fields['sqlHash'];
        if (!in_array($status, ['accepted', 'semantic-inconclusive', 'unsupported', 'finding', 'infrastructure-failure'], true)
            || !in_array($kind, ['production', 'lexeme', 'compound', 'version-case', 'rewrite', 'spacing'], true)
            || preg_match('/\A[0-9a-f]{64}\z/D', $inputHash) !== 1 || preg_match('/\A[0-9a-f]{64}\z/D', $sqlHash) !== 1) {
            throw new CoverageException('Invalid verification witness identity.');
        }
        return new self($status, $kind, $fields['id'], $inputHash, $sqlHash, $fields['code']);
    }

}
