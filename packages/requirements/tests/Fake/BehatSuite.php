<?php

declare(strict_types=1);

namespace Tests\Fake;

/**
 * A Behat suite written into a directory so the Behat runner can execute it.
 *
 * example.feature has a passing scenario at line 2, a failing one at line 4, an undefined one
 * at line 6 and a two-example outline at line 8; line 3 is a step, not a scenario header.
 */
final class BehatSuite
{
    /**
     * Writes behat.yml, example.feature and FeatureContext.php into a directory.
     *
     * @param string $directory The directory, which becomes the runner's working directory
     *
     * @return list<string> The command running the package's Behat
     */
    public static function write(string $directory): array
    {
        file_put_contents($directory . '/behat.yml', "default:\n  autoload:\n    '': .\n  suites:\n    default:\n      paths: [example.feature]\n      contexts: [Sample\\FeatureContext]\n");
        file_put_contents($directory . '/example.feature', <<<'FEATURE'
Feature: Runner verification
  Scenario: Passing behavior
    Given a passing step
  Scenario: Failing behavior
    Given a failing step
  Scenario: Undefined behavior
    Given an undefined step
  Scenario Outline: Multiple examples
    Given a passing step
    Examples:
      | example |
      | one     |
      | two     |

FEATURE);
        mkdir($directory . '/Sample');
        file_put_contents($directory . '/Sample/FeatureContext.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace Sample;

use Behat\Behat\Context\Context;
use RuntimeException;

final class FeatureContext implements Context
{
    /** @Given a passing step */
    public function passing(): void
    {
    }

    /** @Given a failing step */
    public function failing(): void
    {
        throw new RuntimeException('Expected failure.');
    }
}
PHP);
        return [PHP_BINARY, dirname(__DIR__, 2) . '/vendor/bin/behat'];
    }
}
