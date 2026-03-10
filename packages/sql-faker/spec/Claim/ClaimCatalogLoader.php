<?php

declare(strict_types=1);

namespace Spec\Claim;

use InvalidArgumentException;

/**
 * Loads claim definitions from the JSON claim catalogs.
 */
final class ClaimCatalogLoader
{
    /**
     * @var list<string>
     */
    private const EVIDENCE_KINDS = [
        'grammar.no_undefined_references',
        'grammar.no_empty_rules',
        'grammar.entries.present',
        'grammar.entries.terminate',
        'grammar.rules.reachable',
        'grammar.rule.contains_sequence',
        'grammar.rule.not_contains_sequence',
        'generation.generates',
        'generation.sql_matches',
        'outcome.kind_in',
    ];

    /**
     * @return list<ClaimDefinition>
     */
    public function load(string $directory, ?string $level = null, ?string $dialect = null): array
    {
        $files = glob($directory . '/*.json');
        if ($files === false) {
            throw new InvalidArgumentException(sprintf('Unable to read claim catalog directory: %s', $directory));
        }

        sort($files);

        $claims = [];
        $seenIds = [];
        foreach ($files as $file) {
            $json = file_get_contents($file);
            if (!is_string($json)) {
                throw new InvalidArgumentException(sprintf('Unable to read claim catalog file: %s', $file));
            }

            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                throw new InvalidArgumentException(sprintf('Claim catalog file must contain a JSON list: %s', $file));
            }

            foreach ($decoded as $offset => $claimData) {
                if (!is_array($claimData) || !self::isStringKeyedArray($claimData)) {
                    throw new InvalidArgumentException(sprintf('Claim at %s[%d] must be an object.', $file, $offset));
                }
                /** @var array<string, mixed> $claimData */

                $normalizedFile = realpath($file);
                $claim = $this->parseClaim($claimData, sprintf('%s[%d]', is_string($normalizedFile) ? $normalizedFile : $file, $offset));
                if ($level !== null && $claim->level !== $level) {
                    continue;
                }

                if ($dialect !== null && $claim->dialect !== $dialect) {
                    continue;
                }

                if (isset($seenIds[$claim->id])) {
                    throw new InvalidArgumentException(sprintf('Duplicate claim id: %s', $claim->id));
                }

                $seenIds[$claim->id] = true;
                $claims[] = $claim;
            }
        }

        return $claims;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseClaim(array $data, string $location): ClaimDefinition
    {
        $id = $this->requiredString($data, 'id', $location);
        $level = $this->requiredString($data, 'level', $location);
        if (!in_array($level, ['contract', 'spec'], true)) {
            throw new InvalidArgumentException(sprintf('Claim %s has unsupported level: %s', $location, $level));
        }

        $dialect = $this->requiredString($data, 'dialect', $location);
        if (!in_array($dialect, ['mysql', 'postgresql', 'sqlite'], true)) {
            throw new InvalidArgumentException(sprintf('Claim %s has unsupported dialect: %s', $location, $dialect));
        }

        $statement = $this->requiredString($data, 'statement', $location);
        $subject = $data['subject'] ?? null;
        if (!is_array($subject) || !self::isStringKeyedArray($subject)) {
            throw new InvalidArgumentException(sprintf('Claim %s must define a subject object.', $location));
        }
        /** @var array<string, mixed> $subject */

        $subjectKind = $this->requiredString($subject, 'kind', $location . '.subject');
        if (!in_array($subjectKind, ['grammar', 'generation'], true)) {
            throw new InvalidArgumentException(sprintf('Claim %s has unsupported subject kind: %s', $location, $subjectKind));
        }

        $subjectOptions = $subject;
        unset($subjectOptions['kind']);
        foreach ($subjectOptions as $key => $value) {
            if (!is_scalar($value)) {
                throw new InvalidArgumentException(sprintf('Claim %s subject option %s must be scalar.', $location, $key));
            }
        }

        $cases = $this->parseCases($data['cases'] ?? [[]], $location);
        $evidence = $this->parseEvidence($data['evidence'] ?? null, $location);

        /** @var array<string, scalar> $subjectOptions */
        return new ClaimDefinition($id, $level, $dialect, $statement, $location, $subjectKind, $subjectOptions, $cases, $evidence);
    }

    /**
     * @param mixed $cases
     * @return list<array<string, scalar>>
     */
    private function parseCases(mixed $cases, string $location): array
    {
        if (!is_array($cases) || !array_is_list($cases)) {
            throw new InvalidArgumentException(sprintf('Claim %s cases must be a JSON list.', $location));
        }

        $parsed = [];
        foreach ($cases as $index => $case) {
            if (!is_array($case) || !self::isStringKeyedArray($case)) {
                throw new InvalidArgumentException(sprintf('Claim %s case %d must be an object.', $location, $index));
            }
            /** @var array<string, mixed> $case */

            $parameters = $case['parameters'] ?? $case;
            if (!is_array($parameters) || !self::isStringKeyedArray($parameters)) {
                throw new InvalidArgumentException(sprintf('Claim %s case %d parameters must be an object.', $location, $index));
            }
            /** @var array<string, mixed> $parameters */

            $normalized = [];
            foreach ($parameters as $name => $value) {
                if (!is_scalar($value)) {
                    throw new InvalidArgumentException(sprintf('Claim %s case %d parameters must be scalar key/value pairs.', $location, $index));
                }
                $normalized[$name] = $value;
            }
            $parsed[] = $normalized;
        }

        return $parsed === [] ? [[]] : $parsed;
    }

    /**
     * @param mixed $evidence
     * @return list<EvidenceDefinition>
     */
    private function parseEvidence(mixed $evidence, string $location): array
    {
        if (!is_array($evidence) || !array_is_list($evidence) || $evidence === []) {
            throw new InvalidArgumentException(sprintf('Claim %s must define a non-empty evidence list.', $location));
        }

        $definitions = [];
        foreach ($evidence as $index => $entry) {
            if (!is_array($entry) || !self::isStringKeyedArray($entry)) {
                throw new InvalidArgumentException(sprintf('Claim %s evidence %d must be an object.', $location, $index));
            }
            /** @var array<string, mixed> $entry */

            $kind = $this->requiredString($entry, 'kind', sprintf('%s.evidence[%d]', $location, $index));
            if (!in_array($kind, self::EVIDENCE_KINDS, true)) {
                throw new InvalidArgumentException(sprintf('Claim %s evidence %d has unsupported kind: %s', $location, $index, $kind));
            }

            $options = $entry;
            unset($options['kind']);
            /** @var array<string, mixed> $options */
            $definitions[] = new EvidenceDefinition($kind, $options);
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredString(array $data, string $key, string $location): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException(sprintf('Claim %s must define a non-empty string field: %s', $location, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed, mixed> $value
     * @phpstan-assert array<string, mixed> $value
     */
    private static function isStringKeyedArray(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
