CREATE TABLE clients (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL,
    inn TEXT
);

CREATE TABLE trips (
    id INTEGER PRIMARY KEY,
    number TEXT NOT NULL,
    departure_date TEXT NOT NULL,
    vehicle_plate TEXT,
    capacity_kg INTEGER,
    cost REAL NOT NULL,
    status TEXT NOT NULL
);

CREATE TABLE cargo (
    id INTEGER PRIMARY KEY,
    trip_id INTEGER NOT NULL REFERENCES trips(id),
    client_id INTEGER NOT NULL REFERENCES clients(id),
    description TEXT,
    weight_kg REAL NOT NULL,
    volume_m3 REAL,
    pallets INTEGER
);

CREATE INDEX cargo_trip ON cargo(trip_id);

CREATE TABLE invoices (
    id INTEGER PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id),
    period TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft',
    total REAL NOT NULL DEFAULT 0,
    created_at TEXT,
    sent_at TEXT,
    exported_at TEXT
);

CREATE TABLE invoice_lines (
    id INTEGER PRIMARY KEY,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id),
    trip_id INTEGER NOT NULL REFERENCES trips(id),
    amount REAL NOT NULL
);
