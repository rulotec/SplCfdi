<?php

declare(strict_types=1);

namespace SplCfdi\Domain\Rules;

use SplCfdi\Domain\Addendas\{Addenda, AddendaRegistry, CampoAddenda, DatosAddenda, NivelCampo, TipoCampo};
use SplCfdi\Domain\Models\ComprobanteCalculado;
use SplCfdi\Domain\Support\DecimalFormat;

/**
 * Revisa los DatosAddenda contra la definición de campos de su addenda (clave existente, campos
 * conocidos, requeridos, tipo, longitud, patrón, opciones) y luego las reglas propias de la addenda.
 *
 * `campo`: "Addenda.ordenCompra" o "Addenda.Concepto[2].posicion", con los nombres de CampoAddenda.
 */
final class RuleAddenda implements Rule
{
    public function __construct(private readonly AddendaRegistry $addendas) {}

    public function check(ComprobanteCalculado $calculado): array
    {
        $datos = $calculado->comprobante->addenda;
        if ($datos === null) {
            return [];
        }

        $addenda = $this->addendas->get($datos->clave);
        if ($addenda === null) {
            return [new Violation('addenda-desconocida', sprintf(
                'La addenda "%s" no existe. Las disponibles son: %s.', $datos->clave, implode(', ', array_keys($this->addendas->todas()))
            ), 'Addenda')];
        }

        $campos = [];
        $datos = $datos->completadoCon($addenda->porDefecto($calculado->comprobante));

        foreach ($addenda->campos() as $campo) {
            $campos[$campo->nombre] = $campo;
        }

        return [
            ...$this->camposDesconocidos($addenda, $campos, $datos, count($calculado->comprobante->conceptos)),
            ...$this->valores($addenda, $campos, $datos, $calculado),
            ...$addenda->reglas($calculado, $datos),
        ];
    }

    /**
     * Un nombre que la addenda no define suele ser un error de configuración del host.
     *
     * @param array<string, CampoAddenda> $campos
     *
     * @return Violation[]
     */
    private function camposDesconocidos(Addenda $addenda, array $campos, DatosAddenda $datos, int $conceptos): array
    {
        $violaciones = [];
        foreach (array_keys($datos->valores) as $nombre) {
            if (!isset($campos[$nombre])) {
                $violaciones[] = $this->desconocido($addenda, $nombre, "Addenda.$nombre");
            }
        }

        foreach ($datos->conceptos as $indice => $valores) {
            if ($indice < 0 || $indice >= $conceptos) {
                $violaciones[] = new Violation('addenda-concepto-inexistente', sprintf(
                    'Addenda %s: hay datos para el concepto %d, pero el comprobante tiene %d.', $addenda->nombre(), $indice + 1, $conceptos
                ), sprintf('Addenda.Concepto[%d]', $indice + 1));
                continue;
            }
            foreach (array_keys($valores) as $nombre) {
                if (($campos[$nombre] ?? null)?->nivel !== NivelCampo::Concepto) {
                    $violaciones[] = $this->desconocido($addenda, $nombre, 'Addenda.' . Ubicacion::campoConcepto($indice, $nombre));
                }
            }
        }

        return $violaciones;
    }

    private function desconocido(Addenda $addenda, string $nombre, string $campo): Violation
    {
        return new Violation('addenda-campo-desconocido', sprintf(
            'La addenda %s no tiene un dato "%s"%s.', $addenda->nombre(), $nombre, str_contains($campo, 'Concepto[') ? ' por concepto' : ''
        ), $campo);
    }

    /**
     * @param array<string, CampoAddenda> $campos
     *
     * @return Violation[]
     */
    private function valores(Addenda $addenda, array $campos, DatosAddenda $datos, ComprobanteCalculado $calculado): array
    {
        $violaciones = [];
        foreach ($campos as $campo) {
            if ($campo->nivel !== NivelCampo::Concepto) {
                $violaciones[] = $this->valor($campo, $datos->valor($campo->nombre), 'Addenda ' . $addenda->nombre(), "Addenda.{$campo->nombre}");
                continue;
            }
            foreach ($calculado->comprobante->conceptos as $indice => $concepto) {
                $violaciones[] = $this->valor(
                    $campo,
                    $datos->valorConcepto($indice, $campo->nombre),
                    sprintf('Addenda %s, %s', $addenda->nombre(), Ubicacion::concepto($indice, $concepto)),
                    'Addenda.' . Ubicacion::campoConcepto($indice, $campo->nombre),
                );
            }
        }

        return array_values(array_filter($violaciones));
    }

    private function valor(CampoAddenda $campo, ?string $valor, string $lugar, string $ruta): ?Violation
    {
        if ($valor === null) {
            return $campo->requerido
                ? new Violation('addenda-requerido', "$lugar: falta {$campo->etiqueta}.", $ruta)
                : null;
        }

        $prefijo = sprintf('%s: %s "%s"', $lugar, $campo->etiqueta, $valor);

        $error = match ($campo->tipo) {
            TipoCampo::Entero => preg_match('/^-?\d+$/D', $valor) === 1 ? null : 'debe ser un número entero',
            TipoCampo::Decimal => DecimalFormat::matches($valor, null, true) ? null : 'debe ser un número',
            TipoCampo::Opcion => isset($campo->opciones[$valor]) ? null : 'no es válido; los valores permitidos son ' . implode(', ', array_map(
                fn (string $clave, string $descripcion) => "$clave ($descripcion)", array_keys($campo->opciones), $campo->opciones
            )),
            TipoCampo::Texto => null,
        };
        if ($error !== null) {
            return new Violation('addenda-tipo', "$prefijo $error.", $ruta);
        }

        $longitud = Formato::longitud(
            $valor, "$lugar: {$campo->etiqueta}", $ruta, 'addenda-longitud', $campo->longitudMinima, $campo->longitudMaxima,
        );
        if ($longitud !== null) {
            return $longitud;
        }
        if ($campo->patron !== null && preg_match('/^(?:' . str_replace('/', '\/', $campo->patron) . ')$/Du', $valor) !== 1) {
            return new Violation('addenda-patron', sprintf(
                '%s no tiene el formato requerido%s.', $prefijo, $campo->ayuda === null ? '' : ": {$campo->ayuda}"
            ), $ruta);
        }

        return null;
    }
}
