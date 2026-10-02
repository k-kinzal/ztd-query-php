<?php

declare(strict_types=1);

namespace Tests\ModelContract;

use Deriver\Analyzer;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * Declaration metadata carries raw doc comments for integrations without giving them semantic weight.
 */
#[CoversNothing]
#[Small]
final class DocCommentContractTest extends TestCase
{
    private const SOURCE = <<<'PHP'
        <?php
        /** @global wpdb $wpdb */
        function posts() { global $wpdb; return $wpdb; }
        function plain() {}
        /** Repository of users. */
        final class Users {
            use Timestamps;
            /** @var string */
            public $table = 'users', $alias = 'u';
            public $plain;
            /** @var int Maximum rows. */
            public const LIMIT = 10;
            public const PLAIN = 1;
            public function __construct(/** @var \PDO */ public $pdo) {}
            /**
             * @param int $id
             * @return array<string, mixed>
             */
            public function find($id) { return []; }
        }
        trait Timestamps {
            /** @var string|null */
            public $created;
            /** @return string */
            public function touch() { return 'now'; }
        }
        enum Status { /** Visible to readers. */ case Published; case Draft; }
        PHP;

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testFunctionAndMethodSignaturesExposeTheRawDocComment(): void
    {
        $declarations = Analysis::session(self::SOURCE)->declarations();
        self::assertSame('/** @global wpdb $wpdb */', $declarations->signature('posts')?->docComment);
        self::assertSame('', $declarations->signature('plain')?->docComment);
        self::assertSame("/**\n     * @param int \$id\n     * @return array<string, mixed>\n     */", $declarations->signature('Users::find')?->docComment);
        self::assertSame('/** @return string */', $declarations->signature('Users::touch')?->docComment);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testClassPropertyAndConstantMetadataExposeTheRawDocComment(): void
    {
        $class = Analysis::session(self::SOURCE)->declarations()->class('users');
        self::assertNotNull($class);
        self::assertSame('/** Repository of users. */', $class->docComment);
        self::assertSame(['LIMIT' => '/** @var int Maximum rows. */', 'PLAIN' => ''], $class->constantDocComments);
        self::assertSame(['table' => '/** @var string */', 'alias' => '/** @var string */', 'plain' => '', 'pdo' => '/** @var \PDO */', 'created' => '/** @var string|null */'], array_map(static fn ($property): string => $property->docComment, $class->properties));
        self::assertSame(['Published' => '/** Visible to readers. */', 'Draft' => ''], Analysis::session(self::SOURCE)->declarations()->class('Status')?->constantDocComments);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testDocCommentsSurviveGraphReuseAcrossSnapshots(): void
    {
        $analyzer = new Analyzer();
        $analyzer->open(new ProjectInput([new SourceFile('app.php', self::SOURCE)]))->declarations()->signature('posts');
        $later = $analyzer->open(new ProjectInput([new SourceFile('app.php', self::SOURCE), new SourceFile('other.php', '<?php function other() {}')]));
        self::assertSame('/** @global wpdb $wpdb */', $later->declarations()->signature('posts')?->docComment);
    }

    /**
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testPhpDocTypesDoNotChangeDerivedValues(): void
    {
        $documented = Analysis::session('<?php /** @param int $id @return int */ function target($id) { return $id; }')->derive(new ReturnQuery('target'));
        $plain = Analysis::session('<?php function target($id) { return $id; }')->derive(new ReturnQuery('target'));
        self::assertSame('mixed', $documented->normalOutcomes[0]->values['return']->attributes['type'] ?? 'mixed');
        self::assertEquals($plain->normalOutcomes[0]->values['return'], $documented->normalOutcomes[0]->values['return']);
        self::assertEquals($plain->assessment, $documented->assessment);
    }
}
