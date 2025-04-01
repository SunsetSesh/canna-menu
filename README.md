# Wholesale Cannabis Menu System

A PHP-based web application for managing and displaying a wholesale cannabis product menu. Features include an admin panel for managing users, categories, products, plugins, and custom CSS styling for the public-facing menu page.

## Features

- Public Menu (`index.php`): Displays products organized by category with details like strain type, THC/CBD content, price, and images.
- Admin Panel (`admin.php`):
  - User Management: Add, update, and delete admin users.
  - Category Management: Manage product categories.
  - Product Management: Add, edit, and delete products with image uploads.
  - Plugin System: Upload, activate, and deactivate plugins to extend functionality.
  - Custom CSS: Input custom CSS to style the public menu page.
- Plugin Support: Modular system for adding new features via PHP plugins.
- Database-Driven: Uses PDO with MySQL for data storage.

## Requirements

- PHP 7.4 or higher
- MySQL or compatible database
- Web server (e.g., Apache, Nginx)
- Write permissions for the `uploads/` and `plugins/` directories

## Installation

Follow these steps to set up the application:

1. Clone the Repository:
   ```
   git clone https://github.com/yourusername/wholesale-cannabis-menu.git
   cd wholesale-cannabis-menu
   ```


2. Set Up the Database:
Create a MySQL database (e.g., `cannabis_menu`). Use the SQL in the "Database Schema" section below to create the tables manually, or import a provided `schema.sql` file if available.

3. Configure Database Connection:
Copy `config.example.php` to `config.php` and edit it with your database credentials:
```php
<?php
$dsn = 'mysql:host=localhost;dbname=cannabis_menu';
$username = 'your_db_username';
$password = 'your_db_password';
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
```

4. Set Up Directories: Create and set permissions for the required directories:
```
mkdir uploads
chmod 755 uploads
mkdir plugins
chmod 755 plugins
```

5. Deploy to Web Server: Place the project files in your web server’s root directory (e.g., /var/www/html/). Ensure the server has write permissions for the root directory to save custom.css.
6. Access the Application:
   Visit http://yourdomain.com/admin.php to log in (create a user via the admin panel first).
   Visit http://yourdomain.com/index.php to see the public menu.

## Database Schema
Create the database tables with this SQL:
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category_id INT,
    strain_type ENUM('Indica', 'Sativa', 'Hybrid') NOT NULL,
    thc_content FLOAT NOT NULL,
    cbd_content FLOAT NOT NULL,
    price_per_gram DECIMAL(10,2) NOT NULL,
    quantity_available DECIMAL(10,2) NOT NULL,
    unit_type ENUM('grams', 'ounces', 'pounds', 'units') NOT NULL,
    moq DECIMAL(10,2),
    description TEXT,
    image VARCHAR(255),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);
```

## Usage

### Admin Panel

Login: Use an existing admin username and password.
Tabs:
Manage Users: Add/edit/delete admin users.
Manage Categories: Add/edit/delete product categories.
Add Product: Create new products with images.
Manage Products: Edit/delete existing products.
Manage Plugins: Upload and toggle plugins.
Custom CSS: Enter CSS to style index.php.

### Adding Plugins

Create a plugin file (e.g., example.php) in plugins/:
```php
<?php
class ExamplePlugin extends BasePlugin {
    public function header() {
        echo "<!-- Example Plugin Header -->";
    }
}
```
Upload it via the "Manage Plugins" tab or place it in plugins/, then activate it.

### Custom CSS

Go to the "Custom CSS" tab, enter your styles, and save:
```css
.product-card {
    background-color: #f9f9f9;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}
```
CSS is saved to custom.css and applied to index.php.

### File Structure

```
admin.php          # Admin panel
config.php         # Database configuration
custom.css         # Custom CSS file (generated)
index.php          # Public menu page
plugins.php        # Plugin manager
plugins/           # Directory for plugins
plugins/example.php    # Sample plugin
uploads/           # Directory for product images
README.md          # This file
```

### Contributing

- Fork the repository.
- Create a feature branch: git checkout -b feature/your-feature
- Commit changes: git commit -m "Add your feature"
- Push to the branch: git push origin feature/your-feature
- Open a pull request.

### License

This project is licensed under the GNU GPL 3.

### Notes
- Ensure proper file permissions for uploads and plugins.
- For production, secure admin.php with HTTPS and additional authentication layers.
- Consider storing plugin activation state in a database for persistence.
