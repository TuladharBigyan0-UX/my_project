-- 1. Create a new user
CREATE USER 'libraryUser'@'localhost' IDENTIFIED BY 'StrongPassword123';

-- 2. Grant all privileges on the libraryMS database
GRANT ALL PRIVILEGES ON libraryMS.* TO 'libraryUser'@'localhost';

-- 3. Apply changes
FLUSH PRIVILEGES;