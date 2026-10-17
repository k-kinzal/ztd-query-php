<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use Deriver\Model\Registration\ProviderExpansion;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Session\SnapshotIdentity;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SnapshotIdentityTest extends TestCase
{
    /**
     * @throws JsonException If the identity cannot be encoded
     */
    public function testIdDependsOnlyOnSourcesModelsEnvironmentAndTarget(): void
    {
        $snapshotIdentity = static function (ProjectInput $input, Configuration $configuration): SnapshotIdentity {
            $providers = new ProviderExpansion($input, $configuration);
            return new SnapshotIdentity($providers->input, $providers->configuration, new Registry($providers->configuration), $providers);
        };
        $a = new SourceFile('a.php', '<?php function a(){}');
        $b = new SourceFile('b.php', '<?php function b(){}', true);
        $configuration = new Configuration(environment: ['x' => Term::constant(1)], dependencyVersions: ['lib' => '1']);
        $first = $snapshotIdentity(new ProjectInput([$a, $b]), $configuration);
        $second = $snapshotIdentity(new ProjectInput([$b, $a]), $configuration);
        self::assertSame($first->id, $second->id);
        self::assertSame(['a.php', 'b.php'], array_keys($first->sources));
        self::assertSame(['b.php' => 'declarations'], $first->sourceModes);
        self::assertNotSame($first->id, $snapshotIdentity(new ProjectInput([$a]), $configuration)->id);
        self::assertNotSame($first->id, $snapshotIdentity(new ProjectInput([$a, $b]), new Configuration(environment: ['x' => Term::constant(2)], dependencyVersions: ['lib' => '1']))->id);
        self::assertNotSame($first->id, $snapshotIdentity(new ProjectInput([$a, $b]), new Configuration(environment: ['x' => Term::constant(1)], dependencyVersions: ['lib' => '2']))->id);
    }

    /**
     * @throws JsonException If the identity cannot be encoded
     */
    public function testSnapshotPublishesTheIdentityWithTheDeclarationDiagnostics(): void
    {
        $snapshotIdentity = static function (ProjectInput $input, Configuration $configuration): SnapshotIdentity {
            $providers = new ProviderExpansion($input, $configuration);
            return new SnapshotIdentity($providers->input, $providers->configuration, new Registry($providers->configuration), $providers);
        };
        $identity = $snapshotIdentity(new ProjectInput([new SourceFile('a.php', '<?php function a(){}')]), new Configuration());
        $snapshot = $identity->snapshot([]);
        self::assertSame($identity->id, $snapshot->id);
        self::assertSame($identity->sources, $snapshot->sources);
        self::assertSame($identity->models, $snapshot->models);
        self::assertSame([], $snapshot->diagnostics);
    }

}
