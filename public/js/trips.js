$(function () {
    var $tbody = $('#trips tbody');

    function load() {
        $.getJSON('api/trips.php', $('#filter').serialize(), function (data) {
            $tbody.empty();
            if (!data.trips.length) {
                $tbody.append('<tr><td colspan="7" class="empty">Рейсов нет</td></tr>');
                return;
            }
            $.each(data.trips, function (i, t) {
                $tbody.append(
                    '<tr class="status-' + esc(t.status) + '">' +
                    '<td><a href="trip.php?id=' + t.id + '">' + esc(t.number) + '</a></td>' +
                    '<td>' + esc(t.departure) + '</td>' +
                    '<td>' + esc(t.vehicle_plate) + '</td>' +
                    '<td>' + esc(t.status_name) + '</td>' +
                    '<td class="r">' + t.cargo_count + '</td>' +
                    '<td class="r">' + num(t.weight_kg || 0) + '</td>' +
                    '<td class="r">' + money(t.cost) + '</td>' +
                    '</tr>'
                );
            });
        });
    }

    $('#filter select').on('change', load);
    load();
});
