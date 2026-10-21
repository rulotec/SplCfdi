<?php

// Valores que catCFDI.xsd todavía enumera pero que el SAT ya no acepta en CFDI 4.0.
// Se mantiene a mano: `bin/cfdi-catalogs write` no los escribe y `check` no los reporta como nuevos.
// Confirma la vigencia de cada uno en catCFDI.xls antes de agregarlo o quitarlo;
// `bin/cfdi-catalogs xls` avisa de las claves que el XSD lista y el XLS ya no.
return [
    'c_UsoCFDI' => ['P01'],                                // "Por definir": código de CFDI 3.3
    'c_RegimenFiscal' => ['609', '628', '629', '630'],     // no están en c_RegimenFiscal de catCFDI.xls (2026-10-15)
    'c_TipoRelacion' => ['08', '09'],                      // no están en c_TipoRelacion de catCFDI.xls (2026-10-15)
];
