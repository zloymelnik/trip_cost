function esc(s) {
    return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
}

function money(x) {
    return Number(x).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCents(cents) {
    var negative = cents < 0;
    var absolute = negative ? -cents : cents;
    var centsPerRuble = BigInt(100);
    var rubles = (absolute / centsPerRuble).toLocaleString('ru-RU');
    return (negative ? '-' : '') + rubles + ',' + String(absolute % centsPerRuble).padStart(2, '0');
}

function num(x) {
    return Number(x).toLocaleString('ru-RU', { maximumFractionDigits: 1 });
}
