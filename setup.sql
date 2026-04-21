-- Bank Lab - Vulnerable Banking Application Database
-- Run this in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS bank_lab;
USE bank_lab;

-- Users table (stores plaintext passwords - intentionally insecure)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(100) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    avatar_color VARCHAR(20) DEFAULT '#6C63FF',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Accounts table (sequential IDs - vulnerable to IDOR)
CREATE TABLE IF NOT EXISTS accounts (
    id INT PRIMARY KEY,
    user_id INT NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    account_type VARCHAR(50) DEFAULT 'Savings',
    balance DECIMAL(12,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Transactions table (memo stored unescaped - vulnerable to Stored XSS)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_account_id INT NOT NULL,
    to_account_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    memo TEXT,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_account_id) REFERENCES accounts(id),
    FOREIGN KEY (to_account_id) REFERENCES accounts(id)
);

-- Seed users (plaintext passwords)
INSERT INTO users (id, username, password, full_name, email, avatar_color) VALUES
(1, 'alice',   'password123', 'Alice Johnson',  'alice@banklab.test',  '#FF6B9D'),
(2, 'bob',     'qwerty999',   'Bob Martinez',   'bob@banklab.test',    '#43D0AE'),
(3, 'carol',   'carol2024',   'Carol White',    'carol@banklab.test',  '#F5A623'),
(4, 'dave',    'dave1234',    'Dave Thompson',  'dave@banklab.test',   '#7C83FD');

-- Seed accounts (sequential IDs starting at 101 - IDOR target)
INSERT INTO accounts (id, user_id, account_number, account_type, balance) VALUES
(101, 1, 'BA-0001-1001', 'Savings',  12500.75),
(102, 2, 'BA-0002-2001', 'Savings',   4820.00),
(103, 3, 'BA-0003-3001', 'Checking', 31200.50),
(104, 4, 'BA-0004-4001', 'Savings',   9600.00);

-- Seed transactions
INSERT INTO transactions (from_account_id, to_account_id, amount, memo) VALUES
(101, 102, 250.00, 'Lunch split'),
(102, 101, 100.00, 'Coffee reimbursement'),
(103, 101, 500.00, 'Freelance payment'),
(101, 103, 75.00,  'Subscription share'),
(104, 102, 200.00, 'Birthday gift');

-- Cards table
CREATE TABLE IF NOT EXISTS cards (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT NOT NULL,
    label        VARCHAR(100) NOT NULL,
    last4        CHAR(4) NOT NULL,
    expiry       VARCHAR(7) NOT NULL,
    card_type    VARCHAR(20) DEFAULT 'Debit',
    card_brand   VARCHAR(20) DEFAULT 'Visa',
    gradient     VARCHAR(200) DEFAULT 'linear-gradient(135deg,#6C63FF,#A8D8F8)',
    status       ENUM('active','frozen','blocked') DEFAULT 'active',
    credit_limit DECIMAL(10,2) DEFAULT 0.00,
    spent        DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Card requests table
CREATE TABLE IF NOT EXISTS card_requests (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    card_type   VARCHAR(50) NOT NULL,
    status      ENUM('pending','approved','rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seed cards (one per user)
INSERT INTO cards (user_id, label, last4, expiry, card_type, card_brand, gradient, status, credit_limit, spent) VALUES
(1, 'Alice Primary',   '4821', '08/28', 'Debit',  'Visa',       'linear-gradient(135deg,#FF6B9D,#FF8E53)', 'active',   0.00,    0.00),
(1, 'Alice Credit',    '9034', '03/27', 'Credit', 'Mastercard', 'linear-gradient(135deg,#6C63FF,#48CAE4)', 'active',   5000.00, 1240.50),
(2, 'Bob Wallet',      '2267', '11/26', 'Debit',  'Visa',       'linear-gradient(135deg,#43D0AE,#2193B0)', 'active',   0.00,    0.00),
(3, 'Carol Business',  '5510', '06/29', 'Credit', 'Visa',       'linear-gradient(135deg,#F5A623,#F76B1C)', 'active',   10000.00,3200.00),
(4, 'Dave Savings',    '7743', '01/28', 'Debit',  'Mastercard', 'linear-gradient(135deg,#7C83FD,#6C63FF)', 'frozen',   0.00,    0.00);
