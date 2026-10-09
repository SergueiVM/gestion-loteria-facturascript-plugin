function initEditTalonario() {
    if (window.__editTalonarioInitialized) {
        return;
    }
    window.__editTalonarioInitialized = true;

    var state = {
        vendidasTouched: false,
        noVendidasTouched: false,
        isNewRecord: false
    };

    function parseIntValue(value) {
        if (value === null || value === undefined) {
            return 0;
        }

        value = String(value).replace(/[^0-9-]/g, '').trim();
        if (value === '' || value === '-') {
            return 0;
        }

        var parsed = parseInt(value, 10);
        return isNaN(parsed) ? 0 : parsed;
    }

    function getTotal() {
        var totalField = document.querySelector('[name="total_participaciones"]');
        var inicioField = document.querySelector('[name="nro_inicio"]');
        var finField = document.querySelector('[name="nro_fin"]');
        var total = parseIntValue(totalField && totalField.value);

        if (total > 0) {
            return total;
        }

        var inicio = parseIntValue(inicioField && inicioField.value);
        var fin = parseIntValue(finField && finField.value);
        if (fin < inicio) {
            return 0;
        }

        return fin - inicio + 1;
    }

    function syncFrom(sourceName) {
        var total = getTotal();
        if (total <= 0) {
            return;
        }

        var vendidasField = document.querySelector('[name="vendidas"]');
        var noVendidasField = document.querySelector('[name="no_vendidas"]');
        if (!vendidasField || !noVendidasField) {
            return;
        }

        var vendidas = parseIntValue(vendidasField.value);
        var noVendidas = parseIntValue(noVendidasField.value);

        if (sourceName === 'vendidas') {
            state.vendidasTouched = true;
            noVendidasField.value = Math.max(0, total - vendidas);
        } else if (sourceName === 'no_vendidas') {
            state.noVendidasTouched = true;
            vendidasField.value = Math.max(0, total - noVendidas);
        }
    }

    function syncTotalAndCounters() {
        var totalField = document.querySelector('[name="total_participaciones"]');
        var total = getTotal();
        if (totalField) {
            totalField.value = total > 0 ? total : 0;
        }

        var vendidasField = document.querySelector('[name="vendidas"]');
        var noVendidasField = document.querySelector('[name="no_vendidas"]');
        if (!vendidasField || !noVendidasField || total <= 0) {
            return;
        }

        var vendidas = parseIntValue(vendidasField.value);
        var noVendidas = parseIntValue(noVendidasField.value);

        if (state.isNewRecord && !state.vendidasTouched && !state.noVendidasTouched) {
            vendidasField.value = total;
            noVendidasField.value = 0;
            return;
        }

        if (state.vendidasTouched && !state.noVendidasTouched) {
            noVendidasField.value = Math.max(0, total - vendidas);
            return;
        }

        if (!state.vendidasTouched && state.noVendidasTouched) {
            vendidasField.value = Math.max(0, total - noVendidas);
            return;
        }

        if ((vendidas + noVendidas) !== total) {
            if (vendidasField === document.activeElement) {
                noVendidasField.value = Math.max(0, total - vendidas);
            } else if (noVendidasField === document.activeElement) {
                vendidasField.value = Math.max(0, total - noVendidas);
            }
        }
    }

    var nroInicio = document.querySelector('[name="nro_inicio"]');
    var nroFin = document.querySelector('[name="nro_fin"]');
    var vendidasField = document.querySelector('[name="vendidas"]');
    var noVendidasField = document.querySelector('[name="no_vendidas"]');
    var idTalonarioField = document.querySelector('[name="idtalonario"]');

    state.isNewRecord = !idTalonarioField || idTalonarioField.value === '';

    if (nroInicio) {
        ['input', 'change', 'keyup', 'blur'].forEach(function (eventName) {
            nroInicio.addEventListener(eventName, syncTotalAndCounters);
        });
    }
    if (nroFin) {
        ['input', 'change', 'keyup', 'blur'].forEach(function (eventName) {
            nroFin.addEventListener(eventName, syncTotalAndCounters);
        });
    }
    if (vendidasField) {
        ['input', 'change', 'keyup', 'blur'].forEach(function (eventName) {
            vendidasField.addEventListener(eventName, function () {
                syncFrom('vendidas');
            });
        });
    }
    if (noVendidasField) {
        ['input', 'change', 'keyup', 'blur'].forEach(function (eventName) {
            noVendidasField.addEventListener(eventName, function () {
                syncFrom('no_vendidas');
            });
        });
    }

    syncTotalAndCounters();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEditTalonario);
} else {
    initEditTalonario();
}

window.addEventListener('pageshow', initEditTalonario);
