<?php

// Valores que catCFDI.xsd todavía enumera pero que el SAT ya no acepta en CFDI 4.0.
// Se mantiene a mano: `bin/cfdi-catalogs write` no los escribe y `check` no los reporta como nuevos.
// Confirma la vigencia de cada uno en la columna de fechas de catCFDI.xls antes de agregarlo o quitarlo.
return [
    'c_UsoCFDI' => ['P01'],   // "Por definir": código de CFDI 3.3
];
