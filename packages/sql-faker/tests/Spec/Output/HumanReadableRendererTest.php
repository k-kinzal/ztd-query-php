<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Spec\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Spec\Output\HumanReadableRenderer;

#[CoversClass(HumanReadableRenderer::class)]
final class HumanReadableRendererTest extends TestCase
{
    public function testRenderIncludesFingerprintFactsForFailedClaims(): void
    {
        $output = (new HumanReadableRenderer())->render([
            'summary' => [
                'status' => 'failed',
                'scope' => [
                    'command' => 'contract',
                    'dialect' => 'mysql',
                ],
                'claims' => ['total' => 1, 'passed' => 0, 'failed' => 1],
                'cases' => ['total' => 1, 'passed' => 0, 'failed' => 1],
                'checks' => ['total' => 1, 'passed' => 0, 'failed' => 1],
            ],
            'claims' => [
                [
                    'claim_id' => 'MYSQL-CONTRACT-GRAMMAR-FINGERPRINT',
                    'status' => 'failed',
                    'statement' => 'fingerprint must match',
                    'source' => ['location' => '/tmp/claims.json[0]'],
                    'cases' => [
                        [
                            'case_number' => 1,
                            'status' => 'failed',
                            'parameters' => [],
                            'checks' => [
                                [
                                    'status' => 'failed',
                                    'kind' => 'grammar.fingerprint_matches',
                                    'message' => 'grammar fingerprint does not match',
                                    'facts' => [
                                        'subject_kind' => 'snapshot',
                                        'version' => 'mysql-8.4.7',
                                        'expected_sha256' => 'expected-hash',
                                        'actual_sha256' => 'actual-hash',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertStringContainsString('expected sha256: expected-hash', $output);
        self::assertStringContainsString('actual sha256: actual-hash', $output);
        self::assertStringContainsString('version: mysql-8.4.7', $output);
        self::assertStringContainsString('subject: snapshot', $output);
    }
}
