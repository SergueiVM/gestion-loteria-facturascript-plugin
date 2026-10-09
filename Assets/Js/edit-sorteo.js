document.addEventListener('DOMContentLoaded', function () {
    function parseMoney(value) {
        if (value === null || value === undefined) return 0;
        // Remove anything that's not number, dot or comma
        value = String(value).replace(/[^0-9.,-]/g, '').trim();
        if (value === '') return 0;
        // Replace comma with dot and remove thousand separators
        value = value.replace(/\./g, '').replace(/,/g, '.');
        var f = parseFloat(value);
        return isNaN(f) ? 0 : f;
    }

    function formatMoney(value) {
        return (Math.round(value * 100) / 100).toFixed(2);
    }

    function updatePrecio() {
        var jugado = parseMoney(document.querySelector('[name="importe_jugado"]')?.value);
        var donativo = parseMoney(document.querySelector('[name="importe_donativo"]')?.value);
        var precio = (jugado || 0) + (donativo || 0);
        var precioField = document.querySelector('[name="precio_participacion"]');
        if (precioField) {
            precioField.value = formatMoney(precio);
        }
    }

    var jugadoEl = document.querySelector('[name="importe_jugado"]');
    var donativoEl = document.querySelector('[name="importe_donativo"]');
    if (jugadoEl) jugadoEl.addEventListener('input', updatePrecio);
    if (donativoEl) donativoEl.addEventListener('input', updatePrecio);

    function updatePremio() {
        var premiadoEl = document.querySelector('input[type="checkbox"][name="premiado"]');
        var premioEl = document.querySelector('[name="importe_premio"]');
        if (!premiadoEl || !premioEl) return;
        premioEl.required = premiadoEl.checked;
        premioEl.readOnly = !premiadoEl.checked;
        if (premiadoEl.checked) {
            premioEl.min = '0.01';
        } else {
            premioEl.removeAttribute('min');
        }
    }

    var premiadoEl = document.querySelector('input[type="checkbox"][name="premiado"]');
    if (premiadoEl) premiadoEl.addEventListener('change', updatePremio);

    // initialize
    updatePrecio();
    updatePremio();
});
