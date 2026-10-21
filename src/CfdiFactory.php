<?php

declare(strict_types=1);

namespace SplCfdi;

use SplCfdi\Application\CfdiService;
use SplCfdi\Domain\Catalogs\Catalogs;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Contracts\Clock;
use SplCfdi\Domain\Contracts\TipoCambioFix;
use SplCfdi\Domain\Rules\{FormatRuleSet, RuleSet};
use SplCfdi\Domain\Services\{ImpuestoRetenidoAggregator, ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Catalogs\EmbeddedCatalogs;
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;
use SplCfdi\Infrastructure\Time\SystemClock;
use SplCfdi\Infrastructure\Xml\LibXmlSchemaValidator;

/** Composition root: lo único que un host necesita para empezar. */
final class CfdiFactory
{
    /**
     * @param TipoCambioFix|null $tipoCambioFix FIX del Banxico; sin él no se revisa el rango del TipoCambio
     * @param bool               $validarXsd    false omite la validación contra cfdv40.xsd (más rápido, menos revisiones)
     */
    public static function create(
        ?DecimalConfiguration $config = null,
        ?Clock $clock = null,
        ?Catalogs $catalogs = null,
        ?TipoCambioFix $tipoCambioFix = null,
        bool $validarXsd = true,
    ): CfdiService {
        $config ??= DecimalConfiguration::sat();
        $catalogs ??= EmbeddedCatalogs::load();
        $math = new BcMathDecimalMath($config);

        return new CfdiService(
            self::createCalculator($config, $catalogs),
            new SimpleCfdiXmlGenerator(),
            FormatRuleSet::standard($config, $catalogs),
            RuleSet::standard($clock ?? new SystemClock(), $catalogs, $math, $tipoCambioFix),
            $validarXsd ? new LibXmlSchemaValidator() : null,
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
