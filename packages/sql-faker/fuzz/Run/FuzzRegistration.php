<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Run;

use Closure;
use PhpFuzzer\Config;
use SqlFaker\Coverage\CoverageException;
use SqlFaker\Fuzz\Target\SqlSyntaxTarget;
use SqlFaker\Fuzz\Target\VerificationResult;

/**
 * Registers exactly one target and keeps checkpointing outside exploration choices.
 */
final class FuzzRegistration
{
    /**
     * @param Closure(string, string): VerificationResult $verify
     */
    public static function register(Config $config, FuzzSetup $setup, Closure $verify, string $databaseVersion): void
    {
        $checkpoint = $setup->checkpoint($databaseVersion);
        $checkpoint->flush();
        register_shutdown_function(static function () use ($checkpoint): void {
            pcntl_alarm(0);
            try {
                $checkpoint->flush();
                if ($checkpoint->exitCode() !== 0) {
                    exit($checkpoint->exitCode());
                }
            } catch (CoverageException $failure) {
                fwrite(STDERR, 'Coverage infrastructure failure: ' . $failure->getMessage() . "\n");
                exit(2);
            }
        });
        $config->setAllowedExceptions([]);
        $config->setMaxLen(4 + $setup->decoder->maximum * 16);
        $config->setTarget(Closure::fromCallable(new SqlSyntaxTarget($setup->provider->generate(...), $setup->decoder, $verify, $checkpoint)));
    }
}
