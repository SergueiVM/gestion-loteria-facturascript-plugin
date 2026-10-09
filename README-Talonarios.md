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

Ordenación en ListTalonario:
- Número inicio y Contacto permiten ordenar ascendente y descendente desde el desplegable o pulsando el título de la columna.
- Contacto se ordena por su descripción, no por su identificador, y mantiene el enlace a su ficha.
- La ordenación predeterminada es por Número inicio ascendente.
