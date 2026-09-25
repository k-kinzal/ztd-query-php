<?php

declare(strict_types=1);

namespace SqlCatalog\Compatibility;

class_alias(\SqlCatalog\Facade\ConfigurationSchema::class, 'SqlCatalog\ConfigurationSchema');
class_alias(\SqlCatalog\Facade\Analyzer::class, 'SqlCatalog\Analyzer');
class_alias(\SqlCatalog\Facade\AnalysisOptions::class, 'SqlCatalog\AnalysisOptions');
class_alias(\SqlCatalog\Facade\Configuration::class, 'SqlCatalog\Configuration');
class_alias(\SqlCatalog\Facade\InvalidConfigurationException::class, 'SqlCatalog\InvalidConfigurationException');
class_alias(\SqlCatalog\Facade\ExtensionRegistry::class, 'SqlCatalog\Extension\ExtensionRegistry');
class_alias(\SqlCatalog\Facade\LaravelExtension::class, 'SqlCatalog\Extension\LaravelExtension');
class_alias(\SqlCatalog\Core\Extension\SinkCallKind::class, 'SqlCatalog\Extension\SinkCallKind');
class_alias(\SqlCatalog\Extension\Mysqli\MysqliExtension::class, 'SqlCatalog\Extension\MysqliExtension');
class_alias(\SqlCatalog\Core\Extension\SinkRole::class, 'SqlCatalog\Extension\SinkRole');
class_alias(\SqlCatalog\Core\Extension\ExtensionInterface::class, 'SqlCatalog\Extension\ExtensionInterface');
class_alias(\SqlCatalog\Core\Extension\SinkSpec::class, 'SqlCatalog\Extension\SinkSpec');
class_alias(\SqlCatalog\Core\Extension\UnknownExtensionException::class, 'SqlCatalog\Extension\UnknownExtensionException');
class_alias(\SqlCatalog\Extension\WordPress\WordPressExtension::class, 'SqlCatalog\Extension\WordPressExtension');
class_alias(\SqlCatalog\Extension\Doctrine\DoctrineExtension::class, 'SqlCatalog\Extension\DoctrineExtension');
class_alias(\SqlCatalog\Extension\Pdo\PdoExtension::class, 'SqlCatalog\Extension\PdoExtension');
class_alias(\SqlCatalog\Core\Extension\Model\ModelContext::class, 'SqlCatalog\Extension\Model\ModelContext');
class_alias(\SqlCatalog\Core\Extension\Model\QueryModelInterface::class, 'SqlCatalog\Extension\Model\QueryModelInterface');
class_alias(\SqlCatalog\Core\Extension\Model\ModelSet::class, 'SqlCatalog\Extension\Model\ModelSet');
class_alias(\SqlCatalog\Core\Extension\Model\ModelProviderInterface::class, 'SqlCatalog\Extension\Model\ModelProviderInterface');
class_alias(\SqlCatalog\Core\Extension\Model\CallContext::class, 'SqlCatalog\Extension\Model\CallContext');
class_alias(\SqlCatalog\Core\Extension\Model\QueryOutput::class, 'SqlCatalog\Extension\Model\QueryOutput');
class_alias(\SqlCatalog\Core\Analysis\FunctionModel\Registry::class, 'SqlCatalog\Analysis\FunctionModel\Registry');
class_alias(\SqlCatalog\Facade\ReporterRegistry::class, 'SqlCatalog\Reporter\ReporterRegistry');
class_alias(\SqlCatalog\Core\Reporter\UnknownReporterException::class, 'SqlCatalog\Reporter\UnknownReporterException');
class_alias(\SqlCatalog\Core\Reporter\CatalogArtifacts::class, 'SqlCatalog\Reporter\CatalogArtifacts');
class_alias(\SqlCatalog\Facade\HtmlReporter::class, 'SqlCatalog\Reporter\HtmlReporter');
class_alias(\SqlCatalog\Reporter\Text\TextReporter::class, 'SqlCatalog\Reporter\TextReporter');
class_alias(\SqlCatalog\Reporter\Json\JsonReporter::class, 'SqlCatalog\Reporter\JsonReporter');
class_alias(\SqlCatalog\Core\Reporter\ReporterInterface::class, 'SqlCatalog\Reporter\ReporterInterface');
class_alias(\SqlCatalog\Core\Catalog\Severity::class, 'SqlCatalog\Catalog\Severity');
class_alias(\SqlCatalog\Core\Catalog\Catalog::class, 'SqlCatalog\Catalog\Catalog');
class_alias(\SqlCatalog\Core\Catalog\EntryIdentity::class, 'SqlCatalog\Catalog\EntryIdentity');
class_alias(\SqlCatalog\Core\Catalog\AnalysisProblem::class, 'SqlCatalog\Catalog\AnalysisProblem');
class_alias(\SqlCatalog\Core\Catalog\FindingRule::class, 'SqlCatalog\Catalog\FindingRule');
class_alias(\SqlCatalog\Core\Catalog\Finding::class, 'SqlCatalog\Catalog\Finding');
class_alias(\SqlCatalog\Core\Catalog\StatementPart::class, 'SqlCatalog\Catalog\StatementPart');
class_alias(\SqlCatalog\Core\Catalog\Placeholder::class, 'SqlCatalog\Catalog\Placeholder');
class_alias(\SqlCatalog\Core\Catalog\CatalogEntry::class, 'SqlCatalog\Catalog\CatalogEntry');
class_alias(\SqlCatalog\Core\Catalog\Resolution::class, 'SqlCatalog\Catalog\Resolution');
class_alias(\SqlCatalog\Core\Catalog\ValueDomain::class, 'SqlCatalog\Catalog\ValueDomain');
class_alias(\SqlCatalog\Core\Catalog\CallSite::class, 'SqlCatalog\Catalog\CallSite');
