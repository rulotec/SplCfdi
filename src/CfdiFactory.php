<?php

declare(strict_types=1);

namespace SplCfdi;

use SplCfdi\Application\CfdiService;
use SplCfdi\Domain\Configuration\DecimalConfiguration;
use SplCfdi\Domain\Services\{ImpuestoRetenidoAggregator, ImpuestoTrasladadoAggregator, InvoiceCalculator};
use SplCfdi\Infrastructure\Cfdi\SimpleCfdiXmlGenerator;
use SplCfdi\Infrastructure\Math\BcMathDecimalMath;

/** Composition root: lo único que un host necesita para empezar. */
final class CfdiFactory
{
    public static function create(?DecimalConfiguration $config = null): CfdiService
    {
        return new CfdiService(self::createCalculator($config), new SimpleCfdiXmlGenerator());
    }

    public static function createCalculator(?DecimalConfiguration $config = null): InvoiceCalculator
    {
        $config ??= DecimalConfiguration::sat();
        $math = new BcMathDecimalMath($config);   // una sola configuración compartida

        return new InvoiceCalculator(
            $math,
            new ImpuestoTrasladadoAggregator($math),
            new ImpuestoRetenidoAggregator($math),
            $config,
        );
    }
}