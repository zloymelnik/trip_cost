$(function () {
    var $form = $('#billing-form');
    var $month = $('#billing-period');
    var $show = $('#billing-show');
    var $apply = $('#billing-apply');
    var $status = $('#billing-status');
    var $weightNote = $('#billing-weight-note');
    var $results = $('#billing-results');
    var $clients = $('#billing-clients');
    var previewRequest = null;
    var preview = null;

    function amount(value) {
        return formatCents(BigInt(value.replace('.', '')));
    }

    function invoiceStatus(client) {
        if (client.exported_at) {
            return 'Выгружен в 1С';
        }
        if (client.status === 'sent' || client.sent_at) {
            return 'Отправлен';
        }
        return client.status === 'draft' ? 'Черновик' : '';
    }

    function changeLabel(client, applied) {
        if (client.change === 'create') {
            return applied ? 'Создан' : 'Будет создан';
        }
        if (client.change === 'update') {
            if (client.lines_changed && client.diff === '0.00') {
                return applied ? 'Строки обновлены' : 'Изменятся строки';
            }
            return applied ? 'Обновлён' : 'Будет пересчитан';
        }
        return 'Без изменений';
    }

    function renderResults(data, applied) {
        $clients.empty();
        $.each(data.clients, function (_, client) {
            var $invoice = $('<td>').text('—');
            if (client.invoice_id !== null) {
                $invoice.empty().append($('<a>').attr('href', 'invoice.php?id=' + encodeURIComponent(client.invoice_id))
                    .text('№ ' + client.invoice_id));
                var status = invoiceStatus(client);
                if (status) {
                    $invoice.append($('<div>').text(status));
                }
            }
            $clients.append($('<tr>').append(
                $('<td>').text(client.name),
                $invoice,
                $('<td class="r">').text(amount(client.before)),
                $('<td class="r">').text(amount(client.after)),
                $('<td class="r">').text(amount(client.diff)),
                $('<td>').text(changeLabel(client, applied))
            ));
        });

        $('#billing-total-before').text(amount(data.total_before));
        $('#billing-total-after').text(amount(data.total_after));
        $('#billing-total-diff').text(amount(data.total_diff));
        $results.prop('hidden', data.clients.length === 0);

        var trips = data.weight_based_trips.map(function (trip) { return trip.number; });
        $weightNote.text('Стоимость распределена по весу для рейсов: ' + trips.join(', ') + '.')
            .prop('hidden', trips.length === 0);
    }

    function clearPreview() {
        if (previewRequest) {
            previewRequest.abort();
        }
        preview = null;
        $apply.prop('disabled', true);
        $results.prop('hidden', true);
        $weightNote.prop('hidden', true);
        $status.text('');
    }

    function showError(xhr, fallback) {
        $status.text((xhr.responseJSON && xhr.responseJSON.error) || fallback);
    }

    $month.on('input change', clearPreview);
    $form.on('submit', function (event) {
        event.preventDefault();
        clearPreview();
        $status.text('Загрузка предпросмотра…');
        previewRequest = $.getJSON('api/billing_preview.php', { period: $month.val() })
            .done(function (data) {
                preview = data;
                renderResults(data, false);
                $status.text(data.clients.length ? '' : 'За этот месяц нет счетов и рейсов.');
                $apply.prop('disabled', data.clients.length === 0);
            })
            .fail(function (xhr, status) {
                if (status !== 'abort') {
                    showError(xhr, 'Не удалось загрузить предпросмотр.');
                }
            })
            .always(function () {
                previewRequest = null;
            });
    });

    $apply.on('click', function () {
        if (!preview || preview.period !== $month.val()) {
            return;
        }

        var period = preview.period;
        var message = 'Применить пересчёт за ' + period + '?';
        var affected = preview.clients.filter(function (client) {
            return client.change === 'update' && (client.status === 'sent' || client.sent_at || client.exported_at);
        });
        if (affected.length) {
            message += '\n\nИзменятся отправленные или выгруженные счета:\n' + affected.map(function (client) {
                return '№ ' + client.invoice_id + ' — ' + client.name + ' (' + invoiceStatus(client) + ')';
            }).join('\n');
        }
        if (!window.confirm(message)) {
            return;
        }

        preview = null;
        $month.add($show).add($apply).prop('disabled', true);
        $status.text('Применение пересчёта…');
        $.post('api/billing_apply.php', { period: period }, null, 'json')
            .done(function (data) {
                renderResults(data, true);
                $status.text(data.created || data.updated
                    ? 'Счетов создано ' + data.created + ', обновлено ' + data.updated + ', без изменений ' + data.unchanged + '.'
                    : 'Изменений нет.');
            })
            .fail(function (xhr) {
                showError(xhr, 'Не удалось применить пересчёт.');
            })
            .always(function () {
                $month.add($show).prop('disabled', false);
            });
    });
});
