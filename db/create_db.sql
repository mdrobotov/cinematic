
-- таблица фильмов
CREATE TABLE Movies (
    movie_id SERIAL PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    duration_minutes INT CHECK (duration_minutes > 0),
    age_rating INT CHECK (age_rating BETWEEN 0 AND 21),
    release_date DATE,
    genre VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE
);

-- таблица кинозалов
CREATE TABLE Halls (
    hall_id SERIAL PRIMARY KEY,
    hall_number VARCHAR(10) UNIQUE NOT NULL,
    total_seats INT CHECK (total_seats > 0),
    hall_type VARCHAR(20) DEFAULT 'Standard',
    description TEXT
);

-- таблица мест в залах
CREATE TABLE Seats (
    seat_id SERIAL PRIMARY KEY,
    hall_id INT NOT NULL REFERENCES Halls(hall_id) ON DELETE CASCADE,
    row_number INT CHECK (row_number > 0),
    seat_number INT CHECK (seat_number > 0),
    seat_type VARCHAR(20) DEFAULT 'Regular',
    UNIQUE (hall_id, row_number, seat_number)
);

-- таблица клиентов
CREATE TABLE Clients (
    client_id SERIAL PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE CHECK (email LIKE '%@%.%'),
    phone VARCHAR(20) UNIQUE,
    registration_date DATE DEFAULT CURRENT_DATE
);

-- таблица сеансов
CREATE TABLE Sessions (
    session_id SERIAL PRIMARY KEY,
    movie_id INT NOT NULL REFERENCES Movies(movie_id) ON DELETE RESTRICT,
    hall_id INT NOT NULL REFERENCES Halls(hall_id) ON DELETE RESTRICT,
    start_time TIMESTAMP NOT NULL,
    end_time TIMESTAMP NOT NULL CHECK (end_time > start_time),
    base_price DECIMAL(10,2) CHECK (base_price >= 0),
    status VARCHAR(20) DEFAULT 'active'
);

-- таблица билетов
CREATE TABLE Tickets (
    ticket_id SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES Sessions(session_id) ON DELETE RESTRICT,
    seat_id INT NOT NULL REFERENCES Seats(seat_id) ON DELETE RESTRICT,
    client_id INT REFERENCES Clients(client_id) ON DELETE SET NULL,
    price DECIMAL(10,2) CHECK (price >= 0),
    purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'sold',
    UNIQUE (session_id, seat_id)
);

-- для связи сеансов с фильмами
CREATE INDEX idx_sessions_movie_id ON Sessions(movie_id);

-- для связи билетов с сеансами
CREATE INDEX idx_tickets_session_id ON Tickets(session_id);
