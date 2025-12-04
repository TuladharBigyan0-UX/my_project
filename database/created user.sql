-- 1. Create a new user
CREATE USER 'LIBRARY_MS'@'localhost' IDENTIFIED BY '12345';

-- 2. Grant all privileges on the libraryMS database
GRANT ALL PRIVILEGES ON library_db.* TO 'LIBRARY-MS'@'localhost';

-- 3. Apply changes
FLUSH PRIVILEGES;