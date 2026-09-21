/* TABELE */

CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL,
    surname     VARCHAR(50)  NOT NULL,
    email       VARCHAR(100) NOT NULL,
    password    VARCHAR(255) NOT NULL,
    phone       VARCHAR(20)  NULL,
    role        ENUM('client','employee','admin') NOT NULL DEFAULT 'client',
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE equipment_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT NULL,
    CONSTRAINT uq_category_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE equipment (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description TEXT NULL,
    daily_price DECIMAL(8,2) NOT NULL,
    deposit     DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT fk_equipment_category
        FOREIGN KEY (category_id) REFERENCES equipment_categories(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employees (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    description TEXT NULL,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    CONSTRAINT uq_employees_user UNIQUE (user_id),
    CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id) REFERENCES users (id)
            ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employee_equipment (
    employee_id INT UNSIGNED NOT NULL,
    equipment_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (employee_id, equipment_id),
    CONSTRAINT fk_ee_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ee_equipment
        FOREIGN KEY (equipment_id) REFERENCES equipment(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE employee_availabilty (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    CONSTRAINT fk_availability_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT chk_availability_time CHECK (end_time > start_time),
    CONSTRAINT chk_day_of_week CHECK (day_of_week BETWEEN 0 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reservations (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    employee_id  INT UNSIGNED NOT NULL,
    equipment_id INT UNSIGNED NOT NULL,
    rental_start DATETIME NOT NULL,
    rental_end   DATETIME NOT NULL,
    status       ENUM('oczekujaca','potwierdzona','zrealizowana','anulowana')
                 NOT NULL DEFAULT 'oczekujaca',
    comment      TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservation_user
        FOREIGN KEY  (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_reservation_equipment
        FOREIGN KEY (equipment_id) REFERENCES equipment(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_reservation_dates CHECK (rental_end > rental_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

/* DANE */

INSERT INTO users (name, surname, email, password, phone, role, active) VALUES
('Michal', 'Karczewski', 'admin@wypozyczalnia.pl', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ1234567', '600100100', 'admin', 1),
('Piotr', 'Nowak', 'piotr.nowak@wypozyczalnia.pl', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ1234568', '600100101', 'employee', 1),
('Marta', 'Zielinska', 'marta.zielinska@wypozyczalnia.pl','$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ1234569', '600100102', 'employee', 1),
('Jan', 'Wisniewski', 'jan.wisniewski@gmail.com', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ1234570', '600200201', 'client', 1),
('Ewa', 'Krawczyk', 'ewa.krawczyk@gmail.com', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ1234571', '600200202', 'client', 1),
('Tomasz', 'Lewandowski', 'tomasz.lewandowski@gmail.com', '$2y$10$abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ1234572', '600200203', 'client', 1);

INSERT INTO equipment_categories (name, description) VALUES
('Laptopy', 'Laptopy do pracy, nauki i grania'),
('Komputery', 'komputery stacjonarne do pracy, nauki i grania'),
('Konsole', 'Konsole do gier wraz z osprzetem'),
('Akcesoria', 'Myszki, klawiatury, kamery, dyski zewnetrzne');

INSERT INTO equipment (category_id, name, description, daily_price, deposit, active) VALUES
(1, 'Laptop Dell Lattude 5420', 'Intel i5, 16GB RAM, 512GB SSD', 45.00, 500.00, 1),
(1, 'Laptop Lenovo ThinkPad T14', 'Intel i7, 32GB RAM, 1TB SSD', 60.00, 700.00, 1),
(2, 'Komputer 1', 'Intel i7, RTX5060, 16GB RAM, 512B SSD', 50.00, 600.00, 1),
(2, 'Komputer 2', 'AMD Ryzen7 7800X3D, RTX5070ti, 32GB RAM, 1TB SSD', 100.00, 1000.00, 1),
(3, 'Konsola PlayStation 5', 'Wraz z dwoma padami i grami', 50.00, 800.00, 1),
(3, 'Konsola Xbox Series X', 'Wraz z jednym padem', 45.00, 800.00, 1),
(4, 'Kamera internetowa Logitech C920', 'Full HD, mikrofon', 10.00, 100.00, 1),
(4, 'Dysk zewnetrzny SSD 1TB', 'Samsung T7 USB-C', 8.00, 150.00, 1);

INSERT INTO employees (user_id, description, active) VALUES
(2, 'Obsluguje wydawanie i zwrot laptopow oraz komputerow', 1),
(3, 'Obsluguje wydawanie i zwrot konsol oraz akcesoriow', 1);

INSERT INTO employee_equipment (employee_id, equipment_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4),
(2, 5), (2, 6), (2, 7), (2, 8);

INSERT INTO employee_availabilty (employee_id, day_of_week, start_time, end_time) VALUES
(1,1, '09:00:00', '17:00:00'),
(1,2, '09:00:00', '17:00:00'),
(1,3, '09:00:00', '17:00:00'),
(1,4, '09:00:00', '17:00:00'),
(1,5, '09:00:00', '15:00:00'),
(2,1, '10:00:00', '18:00:00'),
(2,2, '10:00:00', '18:00:00'),
(2,3, '10:00:00', '18:00:00'),
(2,4, '10:00:00', '18:00:00'),
(2,6, '10:00:00', '14:00:00');

INSERT INTO reservations (user_id, employee_id, equipment_id, rental_start, rental_end, status, comment) VALUES
(4, 1, 1, '2026-09-20 10:00:00', '2026-09-23 10:00:00', 'potwierdzona', 'Wypozyczenie na projekt szkolny'),
(5, 1, 3, '2026-09-21 09:00:00', '2026-09-22 09:00:00', 'oczekujaca',   NULL),
(6, 2, 5, '2026-09-25 12:00:00', '2026-09-28 12:00:00', 'potwierdzona', 'Wypozyczenie weekendowe'),
(4, 2, 7, '2026-09-15 11:00:00', '2026-09-17 11:00:00', 'zrealizowana', 'Odebrano i zwrocono zgodnie z terminem'),
(5, 1, 2, '2026-09-10 09:00:00', '2026-09-12 09:00:00', 'anulowana',    'Klient odwolal rezerwacje');