$(function () {
    var $split = $('#trip-split');
    var $status = $split.find('[role="status"]');
    var $table = $split.find('table');
    var $rows = $table.find('tbody');

    $.getJSON('api/trip_split.php', { trip_id: $split.attr('data-trip-id') })
        .done(function (data) {
            var totalCents = 0n;
            $.each(data.clients, function (_, client) {
                var cents = BigInt(client.amount.replace('.', ''));
                totalCents += cents;

                $rows.append($('<tr>').append(
                    $('<td>').text(client.name),
                    $('<td class="r">').text(num(client.cargo_count)),
                    $('<td class="r">').text(num(client.weight_kg)),
                    $('<td class="r">').text(num(client.volume_m3)),
                    $('<td class="r">').text(num(client.share * 100)),
                    $('<td class="r">').text(formatCents(cents))
                ));
            });

            $('#trip-split-total').text(formatCents(totalCents));
            $('#trip-split-cost').text(formatCents(BigInt(data.cost.replace('.', ''))));
            $status.text(data.basis === 'weight_kg'
                ? 'Объём груза не указан или равен нулю. Стоимость распределена по весу.'
                : '');
            $table.prop('hidden', false);
        })
        .fail(function (xhr) {
            var error = xhr.responseJSON && xhr.responseJSON.error;
            $status.text(error || 'Не удалось загрузить распределение стоимости.');
        });
});
