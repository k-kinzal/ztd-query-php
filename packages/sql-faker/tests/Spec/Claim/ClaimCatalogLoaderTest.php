<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Claim;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Claim\ClaimCatalogLoader;

#[CoversClass(ClaimCatalogLoader::class)]
final class ClaimCatalogLoaderTest extends TestCase
{
    public function testLoadAcceptsSnapshotSubjectsAndFingerprintEvidence(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-claim-loader-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $claimsPath = $directory . '/claims.json';

        try {
            file_put_contents($claimsPath, json_encode([
                [
                    'id' => 'TEST-SNAPSHOT-CLAIM',
                    'level' => 'contract',
                    'dialect' => 'mysql',
                    'statement' => 'snapshot claim',
                    'subject' => [
                        'kind' => 'snapshot',
                    ],
                    'evidence' => [
                        [
                            'kind' => 'grammar.fingerprint_matches',
                            'sha256' => 'abc123',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            $claims = (new ClaimCatalogLoader())->load($directory);

            self::assertCount(1, $claims);
            self::assertSame('snapshot', $claims[0]->subjectKind);
            self::assertSame('grammar.fingerprint_matches', $claims[0]->evidence[0]->kind);
        } finally {
            @unlink($claimsPath);
            @rmdir($directory);
        }
    }

    public function testLoadAcceptsAlgorithmContractEvidenceKinds(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-claim-loader-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $claimsPath = $directory . '/claims.json';

        try {
            file_put_contents($claimsPath, json_encode([
                [
                    'id' => 'TEST-ALGORITHM-CONTRACT',
                    'level' => 'contract',
                    'dialect' => 'mysql',
                    'statement' => 'algorithm contract claim',
                    'subject' => [
                        'kind' => 'grammar',
                    ],
                    'cases' => [
                        ['seed' => 17],
                    ],
                    'evidence' => [
                        [
                            'kind' => 'grammar.rewrite_steps_match',
                            'step_ids' => ['fixture.step'],
                        ],
                        [
                            'kind' => 'grammar.termination_lengths_match',
                            'lengths' => ['stmt' => 1],
                        ],
                        [
                            'kind' => 'generation.terminals_equal',
                            'terminals' => ['SELECT'],
                        ],
                        [
                            'kind' => 'outcome.phase_is',
                            'phase' => 'prepare',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            $claims = (new ClaimCatalogLoader())->load($directory);

            self::assertCount(1, $claims);
            self::assertSame('grammar.rewrite_steps_match', $claims[0]->evidence[0]->kind);
            self::assertSame('grammar.termination_lengths_match', $claims[0]->evidence[1]->kind);
            self::assertSame('generation.terminals_equal', $claims[0]->evidence[2]->kind);
            self::assertSame('outcome.phase_is', $claims[0]->evidence[3]->kind);
        } finally {
            @unlink($claimsPath);
            @rmdir($directory);
        }
    }

    public function testLoadAcceptsOptionalVersionConstraints(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-claim-loader-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $claimsPath = $directory . '/claims.json';

        try {
            file_put_contents($claimsPath, json_encode([
                [
                    'id' => 'TEST-VERSIONED-CLAIM',
                    'level' => 'spec',
                    'dialect' => 'mysql',
                    'statement' => 'versioned claim',
                    'versions' => ['mysql-8.4.7', 'mysql-9.0.1'],
                    'subject' => [
                        'kind' => 'generation',
                        'start_rule' => 'stmt',
                        'max_depth' => 4,
                    ],
                    'evidence' => [
                        [
                            'kind' => 'generation.generates',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            $claims = (new ClaimCatalogLoader())->load($directory);

            self::assertCount(1, $claims);
            self::assertSame(['mysql-8.4.7', 'mysql-9.0.1'], $claims[0]->versions);
        } finally {
            @unlink($claimsPath);
            @rmdir($directory);
        }
    }

    public function testLoadRejectsUnsupportedSubjectKind(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-claim-loader-' . bin2hex(random_bytes(8));
        mkdir($directory, 0777, true);
        $claimsPath = $directory . '/claims.json';

        try {
            file_put_contents($claimsPath, json_encode([
                [
                    'id' => 'TEST-INVALID-SUBJECT',
                    'level' => 'contract',
                    'dialect' => 'mysql',
                    'statement' => 'invalid subject',
                    'subject' => [
                        'kind' => 'rewrite_program',
                    ],
                    'evidence' => [
                        [
                            'kind' => 'grammar.no_empty_rules',
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('unsupported subject kind');

            (new ClaimCatalogLoader())->load($directory);
        } finally {
            @unlink($claimsPath);
            @rmdir($directory);
        }
    }
}
