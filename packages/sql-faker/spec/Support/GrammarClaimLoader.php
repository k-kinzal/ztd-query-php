<?php

declare(strict_types=1);

namespace Spec\Support;

use InvalidArgumentException;
use JsonException;

final class GrammarClaimLoader
{
    /**
     * @return list<array{claim_id: string, evidence: array<string, mixed>}>
     */
    public static function loadGrammarEvidence(string $claimsPath): array
    {
        $claims = self::decodeClaims($claimsPath);
        $evidenceCases = [];

        foreach ($claims as $claim) {
            $subject = $claim['subject'] ?? null;
            if (!is_array($subject) || ($subject['kind'] ?? null) !== 'grammar') {
                continue;
            }

            $claimId = $claim['id'] ?? null;
            $evidenceList = $claim['evidence'] ?? null;
            if (!is_string($claimId) || !is_array($evidenceList)) {
                throw new InvalidArgumentException('Grammar claims must define an id and an evidence list.');
            }

            foreach ($evidenceList as $evidence) {
                if (!is_array($evidence)) {
                    throw new InvalidArgumentException(sprintf('Claim %s contains a non-object evidence entry.', $claimId));
                }

                $kind = $evidence['kind'] ?? null;
                if (!is_string($kind) || !str_starts_with($kind, 'grammar.')) {
                    continue;
                }

                /** @var array<string, mixed> $evidence */
                $evidenceCases[] = [
                    'claim_id' => $claimId,
                    'evidence' => $evidence,
                ];
            }
        }

        return $evidenceCases;
    }
    /**
     * @return list<array<string, mixed>>
     */
    private static function decodeClaims(string $claimsPath): array
    {
        $json = file_get_contents($claimsPath);
        if (!is_string($json)) {
            throw new InvalidArgumentException(sprintf('Unable to read claims file: %s', $claimsPath));
        }

        try {
            $claims = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('Claims file is not valid JSON: %s', $claimsPath), 0, $e);
        }

        if (!is_array($claims) || !array_is_list($claims)) {
            throw new InvalidArgumentException('Claims file must decode to a JSON array.');
        }

        /** @var list<array<string, mixed>> $claims */
        return $claims;
    }
}
