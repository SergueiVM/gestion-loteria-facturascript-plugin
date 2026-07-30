Talonarios (GestionLoteria)
---------------------------

Implementación de "Talonarios" dentro del plugin GestionLoteria.

Campos:
- `idsorteo`, `nro_inicio`, `nro_fin`, `idcontacto`, `vendidas`, `no_vendidas`.

Reglas:
- `total_participaciones` = `nro_fin - nro_inicio + 1`.
- Por defecto `vendidas` = `total_participaciones`.
- `precio_talonario` = `sorteo.precio_participacion * vendidas + sorteo.importe_donativo * no_vendidas`.

UI: mostrar "participaciones" en lugar de "papeletas".
