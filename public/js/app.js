function esc(s) {
    return $('<div>').text(s === null || s === undefined ? '' : String(s)).html();
}

function money(x) {
    return Number(x).toLocaleString('ru-RU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function num(x) {
    return Number(x).toLocaleString('ru-RU', { maximumFractionDigits: 1 });
}
