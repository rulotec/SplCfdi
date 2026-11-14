<?php

declare(strict_types=1);

namespace SplCfdi;

use SplCfdi\Application\AddendaForm\AddendaFormService;
use SplCfdi\Application\AddendaService;
use SplCfdi\Application\CfdiService;
use SplCfdi\Domain\Addendas\AddendaRegistry;
use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\Clock;
use SplCfdi\Domain\Contracts\TipoCambioFix;
use SplCfdi\Domain\Rules\{FormatRuleSet, RuleAddenda, RuleSet};
use SplCfdi\Domain\Services\{ImpuestoRetenidoAggregator, ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Addendas\{AddendaSchemaValidator, AddendaWriter, AddendaXmlGenerator};
use SplCfdi\Infrastructure\Catalogs\EmbeddedCatalogs;
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Infrastructure\Time\SystemClock;
use SplCfdi\Infrastructure\Xml\{LibXmlSchemaValidator, SchemaValidators};

/** Composition root: lo único que un host necesita para empezar. */
final class CfdiFactory
{
    /**
     * @param TipoCambioFix|null $tipoCambioFix FIX del Banxico; sin él no se revisa el rango del TipoCambio
     * @param bool               $validarXsd    false omite la validación contra cfdv40.xsd y los XSD de las addendas
     * @param AddendaRegistry|null $addendas    por defecto las incluidas; un host agrega las suyas con AddendaRegistry::con()
     */
    public static function create(
        ?DecimalConfiguration $config = null,
        ?Clock $clock = null,
        ?Catalogs $catalogs = null,
        ?TipoCambioFix $tipoCambioFix = null,
        bool $validarXsd = true,
        ?AddendaRegistry $addendas = null,
    ): CfdiService {
        $config ??= DecimalConfiguration::sat();
        $catalogs ??= EmbeddedCatalogs::load();
        $math = new BcMathDecimalMath($config);
        $addendas ??= AddendaRegistry::standard($math);

        return new CfdiService(
            self::createCalculator($config, $catalogs),
            new AddendaXmlGenerator(new SimpleCfdiXmlGenerator(), new AddendaWriter($addendas)),
            FormatRuleSet::standard($config, $catalogs),
            new RuleSet([
                RuleSet::standard($clock ?? new SystemClock(), $catalogs, $math, $tipoCambioFix),
                new RuleAddenda($addendas),
            ]),
            $validarXsd ? new SchemaValidators(new LibXmlSchemaValidator(), new AddendaSchemaValidator($addendas)) : null,
        );
    }

    /** Para agregar la addenda a un CFDI ya timbrado. */
    public static function createAddendaService(
        ?DecimalConfiguration $config = null,
        ?Catalogs $catalogs = null,
        bool $validarXsd = true,
        ?AddendaRegistry $addendas = null,
    ): AddendaService {
        $config ??= DecimalConfiguration::sat();
        $catalogs ??= EmbeddedCatalogs::load();
        $addendas ??= AddendaRegistry::standard(new BcMathDecimalMath($config));

        return new AddendaService(
            self::createCalculator($config, $catalogs),
            FormatRuleSet::standard($config, $catalogs),
            new RuleAddenda($addendas),
            new AddendaWriter($addendas),
            $validarXsd ? new AddendaSchemaValidator($addendas) : null,
        );
    }

    /** Para el formulario donde el usuario captura los datos de la addenda que el host no tiene. */
    public static function createAddendaFormService(
        ?DecimalConfiguration $config = null,
        ?Catalogs $catalogs = null,
        bool $validarXsd = true,
        ?AddendaRegistry $addendas = null,
    ): AddendaFormService {
        $config ??= DecimalConfiguration::sat();
        $catalogs ??= EmbeddedCatalogs::load();
        $addendas ??= AddendaRegistry::standard(new BcMathDecimalMath($config));

        return new AddendaFormService(
            $addendas,
            self::createCalculator($config, $catalogs),
            FormatRuleSet::standard($config, $catalogs),
            new RuleAddenda($addendas),
            new AddendaXmlGenerator(new SimpleCfdiXmlGenerator(), new AddendaWriter($addendas)),
            $validarXsd ? new AddendaSchemaValidator($addendas) : null,
        );
    }

    /** El calculador supone datos con formato válido: valida antes con FormatRuleSet (CfdiService ya lo hace). */
    public static function createCalculator(?DecimalConfiguration $config = null, ?Catalogs $catalogs = null): InvoiceCalculator
    {
        $config ??= DecimalConfiguration::sat();
        $math = new BcMathDecimalMath($config);   // una sola configuración compartida

        return new InvoiceCalculator(
            $math,
            new ImpuestoTrasladadoAggregator($math),
            new ImpuestoRetenidoAggregator($math),
            $config,
            ($catalogs ?? EmbeddedCatalogs::load())->monedas,
        );
    }
}
