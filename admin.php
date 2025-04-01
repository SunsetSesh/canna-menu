<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';
require_once 'plugins.php';

$plugin_manager = new PluginManager($pdo);

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_POST['login'])) {
    showLoginForm();
    exit;
}

// Handle login
if (isset($_POST['login'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$_POST['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($_POST['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
    } else {
        showLoginForm("Invalid username or password");
        exit;
    }
}

// Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

function showLoginForm($error = null) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Admin Login</title>
        <style>
            .login-container { max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ddd; }
            form { display: flex; flex-direction: column; gap: 10px; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if ($error) echo "<p class='error'>$error</p>"; ?>
            <form method="POST">
                <label>Username: <input type="text" name="username" required></label>
                <label>Password: <input type="password" name="password" required></label>
                <button type="submit" name="login">Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}

// Function to get products
function getProducts($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            ORDER BY c.name, p.name
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching products: " . $e->getMessage());
    }
}

// Function to get categories
function getCategories($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching categories: " . $e->getMessage());
    }
}

// Function to get users
function getUsers($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY username");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error fetching users: " . $e->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                throw new Exception("Only JPG, PNG, and GIF files are allowed.");
            }
            $max_size = 5 * 1024 * 1024; // 5MB
            if ($_FILES['image']['size'] > $max_size) {
                throw new Exception("File size must be less than 5MB.");
            }
            
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $image_path = $upload_dir . uniqid() . '-' . basename($_FILES['image']['name']);
            move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
        }

        $plugin_data = ['post' => $_POST, 'image_path' => $image_path];
        $plugin_manager->executeHook('before_action', $plugin_data);

        switch ($_POST['action']) {
            case 'update_product':
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, category_id = ?, strain_type = ?, 
                        thc_content = ?, cbd_content = ?, 
                        price_per_gram = ?, quantity_available = ?, 
                        unit_type = ?, moq = ?, description = ?" . ($image_path ? ", image = ?" : "") . "
                    WHERE id = ?
                ");
                $params = [
                    $_POST['name'],
                    $_POST['category_id'],
                    $_POST['strain_type'],
                    floatval($_POST['thc_content']),
                    floatval($_POST['cbd_content']),
                    floatval($_POST['price_per_gram']),
                    floatval($_POST['quantity_available']),
                    $_POST['unit_type'],
                    $_POST['moq'] ? floatval($_POST['moq']) : null,
                    $_POST['description']
                ];
                if ($image_path) $params[] = $image_path;
                $params[] = intval($_POST['id']);
                $stmt->execute($params);
                $message = "Product updated successfully!";
                break;

            case 'add_product':
                $stmt = $pdo->prepare("
                    INSERT INTO products (name, category_id, strain_type, thc_content, cbd_content, 
                        price_per_gram, quantity_available, unit_type, moq, description, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['category_id'],
                    $_POST['strain_type'],
                    floatval($_POST['thc_content']),
                    floatval($_POST['cbd_content']),
                    floatval($_POST['price_per_gram']),
                    floatval($_POST['quantity_available']),
                    $_POST['unit_type'],
                    $_POST['moq'] ? floatval($_POST['moq']) : null,
                    $_POST['description'],
                    $image_path
                ]);
                $message = "Product added successfully!";
                break;

            case 'delete_product':
                $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
                $stmt->execute([intval($_POST['id'])]);
                $image = $stmt->fetchColumn();
                if ($image && file_exists($image)) {
                    unlink($image);
                }
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([intval($_POST['id'])]);
                $message = "Product deleted successfully!";
                break;

            case 'add_category':
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$_POST['category_name']]);
                $message = "Category added successfully!";
                break;

            case 'update_category':
                $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                $stmt->execute([$_POST['category_name'], intval($_POST['id'])]);
                $message = "Category updated successfully!";
                break;

            case 'delete_category':
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                $stmt->execute([intval($_POST['id'])]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Cannot delete category with existing products!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([intval($_POST['id'])]);
                    $message = "Category deleted successfully!";
                }
                break;

            case 'add_user':
                $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                $stmt->execute([
                    $_POST['username'],
                    password_hash($_POST['password'], PASSWORD_DEFAULT)
                ]);
                $message = "User added successfully!";
                break;

            case 'update_user':
                $params = [$_POST['username']];
                $sql = "UPDATE users SET username = ?";
                if (!empty($_POST['password'])) {
                    $sql .= ", password = ?";
                    $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }
                $sql .= " WHERE id = ?";
                $params[] = intval($_POST['id']);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $message = "User updated successfully!";
                break;

            case 'delete_user':
                if (count(getUsers($pdo)) <= 1) {
                    $error = "Cannot delete the last user!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([intval($_POST['id'])]);
                    $message = "User deleted successfully!";
                }
                break;

            case 'upload_plugin':
                $result = $plugin_manager->uploadPlugin($_FILES['plugin_file']);
                if (strpos($result, "successfully") !== false) {
                    $message = $result;
                } else {
                    $error = $result;
                }
                break;

            case 'activate_plugin':
                if ($plugin_manager->activatePlugin($_POST['plugin_name'])) {
                    $message = "Plugin {$_POST['plugin_name']} activated successfully!";
                } else {
                    $error = "Plugin {$_POST['plugin_name']} is already active or not found.";
                }
                break;

            case 'deactivate_plugin':
                if ($plugin_manager->deactivatePlugin($_POST['plugin_name'])) {
                    $message = "Plugin {$_POST['plugin_name']} deactivated successfully!";
                } else {
                    $error = "Plugin {$_POST['plugin_name']} is not active.";
                }
                break;

            case 'save_css':
                $css_content = $_POST['custom_css'];
                if (file_put_contents('custom.css', $css_content) !== false) {
                    $message = "Custom CSS saved successfully!";
                } else {
                    $error = "Failed to save custom CSS.";
                }
                break;
        }

        $plugin_manager->executeHook('after_action', ['action' => $_POST['action'], 'message' => &$message, 'error' => &$error]);
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$products = getProducts($pdo);
$categories = getCategories($pdo);
$users = getUsers($pdo);

// Plugin hook for modifying data before display
$display_data = $plugin_manager->executeHook('before_display', [
    'products' => &$products,
    'categories' => &$categories,
    'users' => &$users
]);

// Determine active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'users';

// Load existing custom CSS
$custom_css = file_exists('custom.css') ? file_get_contents('custom.css') : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Product Management</title>
    <style>
        .container { max-width: 1200px; margin: 20px auto; display: flex; }
        .sidebar { width: 200px; padding-right: 20px; }
        .sidebar a { display: block; padding: 10px; margin: 5px 0; background: #f0f0f0; text-decoration: none; color: #333; }
        .sidebar a.active { background: #007bff; color: white; }
        .content { flex: 1; padding: 15px; border: 1px solid #ddd; }
        .form-section { margin: 0; padding: 0; }
        form { display: flex; flex-direction: column; gap: 10px; }
        label { font-weight: bold; }
        .error { color: red; }
        .success { color: green; }
        .product-list, .category-list, .user-list, .plugin-list { margin-top: 20px; }
        .product-item, .category-item, .user-item, .plugin-item { border: 1px solid #ddd; padding: 10px; margin: 5px 0; }
        .delete-btn, .action-btn { color: red; cursor: pointer; }
        .action-btn.activate { color: green; }
        .product-image { max-width: 100px; margin-top: 10px; }
        textarea.css-editor { width: 100%; height: 400px; font-family: monospace; }
    </style>
    <?php $plugin_manager->executeHook('header'); ?>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h2>Admin Menu</h2>
            <a href="?tab=users" class="<?php echo $active_tab === 'users' ? 'active' : ''; ?>">Manage Users</a>
            <a href="?tab=categories" class="<?php echo $active_tab === 'categories' ? 'active' : ''; ?>">Manage Categories</a>
            <a href="?tab=add_product" class="<?php echo $active_tab === 'add_product' ? 'active' : ''; ?>">Add Product</a>
            <a href="?tab=products" class="<?php echo $active_tab === 'products' ? 'active' : ''; ?>">Manage Products</a>
            <a href="?tab=plugins" class="<?php echo $active_tab === 'plugins' ? 'active' : ''; ?>">Manage Plugins</a>
            <a href="?tab=custom_css" class="<?php echo $active_tab === 'custom_css' ? 'active' : ''; ?>">Custom CSS</a>
            <form method="POST" style="margin-top: 20px;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" style="width: 100%; padding: 10px; background: #dc3545; color: white; border: none; cursor: pointer;">Logout</button>
            </form>
        </div>

        <div class="content">
            <h1>Product Management</h1>
            <?php 
            if (isset($error)) echo "<p class='error'>$error</p>";
            if (isset($message)) echo "<p class='success'>$message</p>";
            ?>

            <?php if ($active_tab === 'users'): ?>
                <!-- User Management -->
                <div class="form-section">
                    <h2>Manage Users</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_user">
                        <label>Username: <input type="text" name="username" required></label>
                        <label>Password: <input type="password" name="password" required></label>
                        <button type="submit">Add User</button>
                    </form>
                    
                    <div class="user-list">
                        <?php if (empty($users)): ?>
                            <p>No users found.</p>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <div class="user-item">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                    <label>Username: <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></label>
                                    <label>Password: <input type="password" name="password" placeholder="Leave blank to keep current"></label>
                                    <button type="submit">Update</button>
                                    <button type="button" class="delete-btn" onclick="if(confirm('Delete this user?')) { 
                                        let f = document.createElement('form'); 
                                        f.method = 'post'; 
                                        f.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'delete_user\'><input type=\'hidden\' name=\'id\' value=\'<?php echo $user['id']; ?>\'>'; 
                                        document.body.appendChild(f); 
                                        f.submit(); 
                                    }">Delete</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($active_tab === 'categories'): ?>
                <!-- Category Management -->
                <div class="form-section">
                    <h2>Manage Categories</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_category">
                        <label>New Category: <input type="text" name="category_name" required></label>
                        <button type="submit">Add Category</button>
                    </form>
                    
                    <div class="category-list">
                        <?php if (empty($categories)): ?>
                            <p>No categories found.</p>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                            <div class="category-item">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_category">
                                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                    <label>
                                        <input type="text" name="category_name" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                                    </label>
                                    <button type="submit">Update</button>
                                    <button type="button" class="delete-btn" onclick="if(confirm('Delete this category? Only possible if no products use it.')) { 
                                        let f = document.createElement('form'); 
                                        f.method = 'post'; 
                                        f.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'delete_category\'><input type=\'hidden\' name=\'id\' value=\'<?php echo $category['id']; ?>\'>'; 
                                        document.body.appendChild(f); 
                                        f.submit(); 
                                    }">Delete</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($active_tab === 'add_product'): ?>
                <!-- Add New Product Form -->
                <div class="form-section">
                    <h2>Add New Product</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_product">
                        <label>Name: <input type="text" name="name" required></label>
                        <label>Category: 
                            <select name="category_id" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Strain Type: 
                            <select name="strain_type" required>
                                <option value="Indica">Indica</option>
                                <option value="Sativa">Sativa</option>
                                <option value="Hybrid">Hybrid</option>
                            </select>
                        </label>
                        <label>THC %: <input type="number" step="0.1" name="thc_content" required></label>
                        <label>CBD %: <input type="number" step="0.1" name="cbd_content" required></label>
                        <label>Price per Unit: <input type="number" step="0.01" name="price_per_gram" required></label>
                        <label>Quantity: <input type="number" step="0.01" name="quantity_available" required></label>
                        <label>Unit Type:
                            <select name="unit_type" required>
                                <option value="grams">Grams</option>
                                <option value="ounces">Ounces</option>
                                <option value="pounds">Pounds</option>
                                <option value="units">Units</option>
                            </select>
                        </label>
                        <label>MOQ: <input type="number" step="0.01" name="moq" placeholder="Optional"></label>
                        <label>Description: <textarea name="description"></textarea></label>
                        <label>Image: <input type="file" name="image" accept="image/*"></label>
                        <?php $plugin_manager->executeHook('add_product_form'); ?>
                        <button type="submit">Add Product</button>
                    </form>
                </div>

            <?php elseif ($active_tab === 'products'): ?>
                <!-- Existing Products -->
                <div class="form-section">
                    <h2>Manage Existing Products</h2>
                    <div class="product-list">
                        <?php if (empty($products)): ?>
                            <p>No products found.</p>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                            <div class="product-item">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="update_product">
                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                    <label>Name: <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required></label>
                                    <label>Category: 
                                        <select name="category_id" required>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] === $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Strain: 
                                        <select name="strain_type" required>
                                            <option value="Indica" <?php echo $product['strain_type'] === 'Indica' ? 'selected' : ''; ?>>Indica</option>
                                            <option value="Sativa" <?php echo $product['strain_type'] === 'Sativa' ? 'selected' : ''; ?>>Sativa</option>
                                            <option value="Hybrid" <?php echo $product['strain_type'] === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                        </select>
                                    </label>
                                    <label>THC %: <input type="number" step="0.1" name="thc_content" value="<?php echo $product['thc_content']; ?>" required></label>
                                    <label>CBD %: <input type="number" step="0.1" name="cbd_content" value="<?php echo $product['cbd_content']; ?>" required></label>
                                    <label>Price per Unit: <input type="number" step="0.01" name="price_per_gram" value="<?php echo $product['price_per_gram']; ?>" required></label>
                                    <label>Quantity: <input type="number" step="0.01" name="quantity_available" value="<?php echo $product['quantity_available']; ?>" required></label>
                                    <label>Unit Type:
                                        <select name="unit_type" required>
                                            <option value="grams" <?php echo $product['unit_type'] === 'grams' ? 'selected' : ''; ?>>Grams</option>
                                            <option value="ounces" <?php echo $product['unit_type'] === 'ounces' ? 'selected' : ''; ?>>Ounces</option>
                                            <option value="pounds" <?php echo $product['unit_type'] === 'pounds' ? 'selected' : ''; ?>>Pounds</option>
                                            <option value="units" <?php echo $product['unit_type'] === 'units' ? 'selected' : ''; ?>>Units</option>
                                        </select>
                                    </label>
                                    <label>MOQ: <input type="number" step="0.01" name="moq" value="<?php echo $product['moq']; ?>" placeholder="Optional"></label>
                                    <label>Desc: <textarea name="description"><?php echo htmlspecialchars($product['description']); ?></textarea></label>
                                    <label>Image: <input type="file" name="image" accept="image/*"></label>
                                    <?php if ($product['image']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Product Image" class="product-image">
                                    <?php endif; ?>
                                    <?php $plugin_manager->executeHook('update_product_form', ['product' => $product]); ?>
                                    <button type="submit">Update</button>
                                    <button type="button" class="delete-btn" onclick="if(confirm('Delete this product?')) { 
                                        let f = document.createElement('form'); 
                                        f.method = 'post'; 
                                        f.innerHTML = '<input type=\'hidden\' name=\'action\' value=\'delete_product\'><input type=\'hidden\' name=\'id\' value=\'<?php echo $product['id']; ?>\'>'; 
                                        document.body.appendChild(f); 
                                        f.submit(); 
                                    }">Delete</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($active_tab === 'plugins'): ?>
                <!-- Plugin Management -->
                <div class="form-section">
                    <h2>Manage Plugins</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_plugin">
                        <label>Upload Plugin: <input type="file" name="plugin_file" accept=".php" required></label>
                        <button type="submit">Upload</button>
                    </form>
                    
                    <div class="plugin-list">
                        <h3>Available Plugins</h3>
                        <?php
                        $active_plugins = $plugin_manager->getActivePlugins();
                        $available_plugins = $plugin_manager->getAvailablePlugins();
                        if (empty($available_plugins)): ?>
                            <p>No plugins found.</p>
                        <?php else: ?>
                            <?php foreach ($available_plugins as $plugin): ?>
                            <div class="plugin-item">
                                <form method="POST" style="display: inline;">
                                    <span><?php echo htmlspecialchars($plugin); ?></span>
                                    <?php if (in_array($plugin, $active_plugins)): ?>
                                        <input type="hidden" name="action" value="deactivate_plugin">
                                        <input type="hidden" name="plugin_name" value="<?php echo $plugin; ?>">
                                        <button type="submit" class="action-btn">Deactivate</button>
                                    <?php else: ?>
                                        <input type="hidden" name="action" value="activate_plugin">
                                        <input type="hidden" name="plugin_name" value="<?php echo $plugin; ?>">
                                        <button type="submit" class="action-btn activate">Activate</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($active_tab === 'custom_css'): ?>
                <!-- Custom CSS -->
                <div class="form-section">
                    <h2>Custom CSS for Index Page</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="save_css">
                        <label>Custom CSS:
                            <textarea name="custom_css" class="css-editor"><?php echo htmlspecialchars($custom_css); ?></textarea>
                        </label>
                        <button type="submit">Save CSS</button>
                    </form>
                    <p><em>This CSS will be applied to the index.php page only.</em></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php $plugin_manager->executeHook('footer'); ?>
</body>
</html>