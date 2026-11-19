if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./sw.js').catch(function (error) {
        console.error('No se pudo registrar el Service Worker:', error);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    var saleFilterBy = document.getElementById('sale-filter-by');
    var saleFilterValue = document.getElementById('sale-filter-value');
    if (saleFilterBy && saleFilterValue) {
        var filterPlaceholders = {
            buyer_name: 'Escribe el nombre o parte del nombre',
            date: '',
            number: 'Escribe el número de comanda',
            status: 'Elige o escribe un estado',
            neighborhood: 'Escribe el barrio o parte del barrio',
            phone: 'Escribe el celular completo o una parte'
        };
        function updateSaleFilterPlaceholder() {
            var isDateFilter = saleFilterBy.value === 'date';
            saleFilterValue.type = isDateFilter ? 'date' : 'text';
            if (isDateFilter) {
                saleFilterValue.removeAttribute('list');
            } else {
                saleFilterValue.setAttribute('list', 'sale-filter-statuses');
            }
            saleFilterValue.placeholder = filterPlaceholders[saleFilterBy.value] || 'Elige un criterio primero';
        }
        saleFilterBy.addEventListener('change', updateSaleFilterPlaceholder);
        updateSaleFilterPlaceholder();
    }

    var pairCount = document.getElementById('pair-count');
    var pairLines = document.getElementById('pair-lines');
    var pairTemplate = document.getElementById('pair-template');
    var deliveryFee = document.getElementById('delivery-fee');
    var subtotalOutput = document.getElementById('sale-subtotal');
    var totalCollected = document.getElementById('total-collected');
    var resetTotal = document.getElementById('reset-total');

    if (!pairCount || !pairLines || !pairTemplate || !deliveryFee || !subtotalOutput || !totalCollected) {
        return;
    }

    function updatePairNames() {
        pairLines.querySelectorAll('.pair-line').forEach(function (row, index) {
            row.dataset.pairIndex = String(index);
            row.querySelector('.pair-heading strong').textContent = 'Par ' + (index + 1);
            row.querySelectorAll('input[type="radio"]').forEach(function (input) {
                input.name = 'pair_size[' + index + ']';
            });
        });
    }

    function recalculateTotal() {
        var subtotalCents = 0;
        pairLines.querySelectorAll('.pair-product').forEach(function (select) {
            var option = select.options[select.selectedIndex];
            var amount = option ? Number(option.dataset.price || 0) : 0;
            subtotalCents += Math.round(amount * 100);
        });

        var feeCents = Math.round(Number(deliveryFee.value || 0) * 100);
        subtotalOutput.textContent = '$' + (subtotalCents / 100).toFixed(2);
        if (totalCollected.dataset.manual !== 'true') {
            totalCollected.value = ((subtotalCents + feeCents) / 100).toFixed(2);
        }
    }

    function resizePairRows() {
        var count = Math.max(1, Math.min(30, parseInt(pairCount.value, 10) || 1));
        pairCount.value = String(count);
        while (pairLines.querySelectorAll('.pair-line').length < count) {
            var index = pairLines.querySelectorAll('.pair-line').length;
            var html = pairTemplate.innerHTML
                .replace(/__INDEX__/g, String(index))
                .replace(/__NUMBER__/g, String(index + 1));
            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            pairLines.appendChild(wrapper.firstElementChild);
        }
        while (pairLines.querySelectorAll('.pair-line').length > count) {
            pairLines.lastElementChild.remove();
        }
        updatePairNames();
        recalculateTotal();
    }

    pairCount.addEventListener('change', resizePairRows);
    pairLines.addEventListener('change', recalculateTotal);
    deliveryFee.addEventListener('input', recalculateTotal);
    totalCollected.addEventListener('input', function () {
        totalCollected.dataset.manual = 'true';
    });
    if (resetTotal) {
        resetTotal.addEventListener('click', function () {
            totalCollected.dataset.manual = 'false';
            recalculateTotal();
        });
    }

    resizePairRows();
});
