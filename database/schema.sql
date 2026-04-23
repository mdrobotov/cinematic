-- Проверка ключей для sqlite
PRAGMA foreign_keys = ON;

-- Таблица фильмы
CREATE TABLE Movies (
    movie_id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    duration_minutes INTEGER CHECK (duration_minutes > 0),
    age_rating INTEGER CHECK (age_rating BETWEEN 0 AND 21),
    release_date DATE,
    genre VARCHAR(50),
    is_active BOOLEAN DEFAULT 1
);

-- Таблица залы
CREATE TABLE Halls (
    hall_id INTEGER PRIMARY KEY AUTOINCREMENT,
    hall_number VARCHAR(10) UNIQUE NOT NULL,
    total_seats INTEGER CHECK (total_seats > 0),
    hall_type VARCHAR(20) DEFAULT 'Standard',
    description TEXT
);

-- Таблица места
CREATE TABLE Seats (
    seat_id INTEGER PRIMARY KEY AUTOINCREMENT,
    hall_id INTEGER NOT NULL,
    row_number INTEGER CHECK (row_number > 0),
    seat_number INTEGER CHECK (seat_number > 0),
    seat_type VARCHAR(20) DEFAULT 'Regular',
    UNIQUE (hall_id, row_number, seat_number),
    FOREIGN KEY (hall_id) REFERENCES Halls(hall_id) ON DELETE CASCADE
);

-- Таблица клиенты
CREATE TABLE Clients (
    client_id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    registration_date DATE DEFAULT CURRENT_DATE
);
CREATE INDEX idx_clients_email ON Clients(email);

CREATE TRIGGER validate_email_before_insert_clients
BEFORE INSERT ON Clients
BEGIN
    SELECT CASE
        WHEN NEW.email NOT LIKE '%@%.%' AND NEW.email IS NOT NULL
        THEN RAISE(ABORT, 'Invalid email format')
    END;
END;

CREATE TRIGGER validate_email_before_update_clients
BEFORE UPDATE OF email ON Clients
BEGIN
    SELECT CASE
        WHEN NEW.email NOT LIKE '%@%.%' AND NEW.email IS NOT NULL
        THEN RAISE(ABORT, 'Invalid email format')
    END;
END;

--Таблица сеансы
CREATE TABLE Sessions (
    session_id INTEGER PRIMARY KEY AUTOINCREMENT,
    movie_id INTEGER NOT NULL,
    hall_id INTEGER NOT NULL,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL,
    base_price DECIMAL(10,2) CHECK (base_price >= 0),
    status VARCHAR(20) DEFAULT 'active',
    available_seats INTEGER NOT NULL,
    CHECK (end_time > start_time),
    FOREIGN KEY (movie_id) REFERENCES Movies(movie_id) ON DELETE RESTRICT,
    FOREIGN KEY (hall_id) REFERENCES Halls(hall_id) ON DELETE RESTRICT
);
CREATE INDEX idx_sessions_movie_id ON Sessions(movie_id);
CREATE INDEX idx_sessions_start_time ON Sessions(start_time);

-- Таблица билеты
CREATE TABLE Tickets (
    ticket_id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id INTEGER NOT NULL,
    seat_id INTEGER NOT NULL,
    client_id INTEGER,
    price DECIMAL(10,2) CHECK (price >= 0),
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'sold',
    UNIQUE (session_id, seat_id),
    FOREIGN KEY (session_id) REFERENCES Sessions(session_id) ON DELETE RESTRICT,
    FOREIGN KEY (seat_id) REFERENCES Seats(seat_id) ON DELETE RESTRICT,
    FOREIGN KEY (client_id) REFERENCES Clients(client_id) ON DELETE SET NULL
);
CREATE INDEX idx_tickets_session_id ON Tickets(session_id);
CREATE INDEX idx_tickets_purchase_date ON Tickets(purchase_date);
CREATE INDEX idx_tickets_status ON Tickets(status);

-- Представление активные фильмы
CREATE VIEW ActiveMovies AS
SELECT movie_id, title, duration_minutes, age_rating, genre
FROM Movies
WHERE is_active = 1;

-- Предаставление популярные фильмы
CREATE VIEW PopularMovies AS
SELECT 
    m.title,
    COUNT(t.ticket_id) AS tickets_sold
FROM Movies m
JOIN Sessions s ON m.movie_id = s.movie_id
JOIN Tickets t ON s.session_id = t.session_id
WHERE t.status = 'sold'
GROUP BY m.movie_id, m.title
HAVING COUNT(t.ticket_id) > 0;

-- Триггеры для available_seats
CREATE TRIGGER update_available_seats_insert
AFTER INSERT ON Tickets
WHEN NEW.status IN ('sold', 'booked')
BEGIN
    UPDATE Sessions
    SET available_seats = available_seats - 1
    WHERE session_id = NEW.session_id;
END;

CREATE TRIGGER update_available_seats_delete
AFTER DELETE ON Tickets
WHEN OLD.status IN ('sold', 'booked')
BEGIN
    UPDATE Sessions
    SET available_seats = available_seats + 1
    WHERE session_id = OLD.session_id;
END;

CREATE TRIGGER update_available_seats_update
AFTER UPDATE OF status ON Tickets
BEGIN
    UPDATE Sessions
    SET available_seats = available_seats + 1
    WHERE session_id = NEW.session_id
      AND OLD.status IN ('sold', 'booked')
      AND NEW.status NOT IN ('sold', 'booked');
    
    UPDATE Sessions
    SET available_seats = available_seats - 1
    WHERE session_id = NEW.session_id
      AND OLD.status NOT IN ('sold', 'booked')
      AND NEW.status IN ('sold', 'booked');
END;

-- Тестовые данные
INSERT INTO Movies (title, description, duration_minutes, age_rating, release_date, genre, is_active) VALUES
('Дюна 2', 'Очень интересно', 166, 12, '2024-03-01', 'Научная фантастика', 1),
('Вот это драма!', 'Очень смешно', 105, 18, '2026-04-09', 'Романтическая комедия', 1),
('Брат', 'Очень по-русски', 96, 18, '1997-05-17', 'Криминальная драма', 0);

INSERT INTO Halls (hall_number, total_seats, hall_type) VALUES
('Зал 1', 50, 'Standard'),
('Зал 2', 30, 'VIP');

INSERT INTO Seats (hall_id, row_number, seat_number) 
SELECT 1, r, s
FROM (SELECT 1 as r UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) r,
     (SELECT 1 as s UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) s;

INSERT INTO Seats (hall_id, row_number, seat_number) 
SELECT 2, r, s
FROM (SELECT 1 as r UNION SELECT 2 UNION SELECT 3) r,
     (SELECT 1 as s UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10) s;

INSERT INTO Sessions (movie_id, hall_id, start_time, end_time, base_price, available_seats)
VALUES 
(1, 1, '2025-05-01 18:00:00', '2025-05-01 20:46:00', 350, 50),
(2, 2, '2025-05-02 19:00:00', '2025-05-02 20:54:00', 400, 30);

INSERT INTO Clients (first_name, last_name, email, phone) VALUES
('Иван', 'Петров', 'ivan@test.com', '123456789'),
('Мария', 'Сидорова', 'maria@test.com', '987654321');

INSERT INTO Tickets (session_id, seat_id, client_id, price, status) VALUES
(1, 1, 1, 350, 'sold'),
(1, 2, 1, 350, 'sold'),
(2, 51, 2, 400, 'sold');