
USE library_db;

-- ====================================
-- 1. BOOKS TABLE (if not exists)
-- ====================================
CREATE TABLE books (
    book_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(150) NOT NULL,
    isbn VARCHAR(20),
    category VARCHAR(100),
    publisher VARCHAR(150),
    publication_year INT,
    total_copies INT NOT NULL DEFAULT 1,
    available_copies INT NOT NULL DEFAULT 1,
    shelf_location VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_title (title),
    INDEX idx_author (author),
    INDEX idx_category (category)
);

-- ====================================
-- 2. ISSUES TABLE (Book Transactions)
-- ====================================
CREATE TABLE issues (
    issue_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    return_date DATE NULL,
    fine_amount DECIMAL(10,2) DEFAULT 0.00,
    fine_paid TINYINT(1) DEFAULT 0,
    status ENUM('issued', 'returned', 'overdue') DEFAULT 'issued',
    issued_by INT,
    returned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_book (book_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- ====================================
-- 3. CATEGORIES TABLE (Optional)
-- ====================================
CREATE TABLE  categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ====================================
-- 4. RESERVATIONS TABLE (Optional)
-- ====================================
CREATE TABLE  reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    book_id INT NOT NULL,
    user_id INT NOT NULL,
    reservation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'fulfilled', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (book_id) REFERENCES books(book_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status)
);

-- ====================================
-- INSERT SAMPLE DATA
-- ====================================

-- Sample Books
INSERT INTO books (title, author, isbn, category, publisher, publication_year, total_copies, available_copies, shelf_location) VALUES
('The Great Gatsby', 'F. Scott Fitzgerald', '9780743273565', 'Fiction', 'Scribner', 1925, 3, 3, 'A-101'),
('To Kill a Mockingbird', 'Harper Lee', '9780061120084', 'Fiction', 'Harper Perennial', 1960, 2, 2, 'A-102'),
('1984', 'George Orwell', '9780451524935', 'Fiction', 'Signet Classic', 1949, 4, 4, 'A-103'),
('Pride and Prejudice', 'Jane Austen', '9780141439518', 'Romance', 'Penguin Classics', 1813, 2, 2, 'B-201'),
('The Catcher in the Rye', 'J.D. Salinger', '9780316769174', 'Fiction', 'Little Brown', 1951, 3, 3, 'A-104'),
('Introduction to Algorithms', 'Thomas H. Cormen', '9780262033848', 'Computer Science', 'MIT Press', 2009, 5, 5, 'C-301'),
('Clean Code', 'Robert C. Martin', '9780132350884', 'Computer Science', 'Prentice Hall', 2008, 3, 3, 'C-302'),
('The Art of War', 'Sun Tzu', '9781599869773', 'Philosophy', 'Pax Librorum', 2009, 2, 2, 'D-401'),
('Sapiens', 'Yuval Noah Harari', '9780062316097', 'History', 'Harper', 2015, 4, 4, 'E-501'),
('Atomic Habits', 'James Clear', '9780735211292', 'Self-Help', 'Avery', 2018, 3, 3, 'F-601');

-- Sample Categories
INSERT INTO categories (category_name, description) VALUES
('Fiction', 'Fictional literature and novels'),
('Non-Fiction', 'Factual books and real-life stories'),
('Science', 'Scientific books and research'),
('History', 'Historical books and biographies'),
('Technology', 'Books about technology and computers'),
('Self-Help', 'Personal development and motivation'),
('Romance', 'Romantic novels and stories'),
('Mystery', 'Mystery and thriller books'),
('Biography', 'Life stories and autobiographies'),
('Children', 'Books for children and young readers');