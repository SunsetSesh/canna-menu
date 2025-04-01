<?php
require_once 'config.php';
require_once 'plugins.php';

$plugin_manager = new PluginManager($pdo);

function getProducts($pdo) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY c.name, p.name
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$products = getProducts($pdo);

// Function to get unit abbreviation
function getUnitAbbreviation($unit_type) {
    switch ($unit_type) {
        case 'grams': return 'g';
        case 'ounces': return 'oz';
        case 'pounds': return 'lb';
        case 'units': return 'unit';
        default: return '';
    }
}

$plugin_manager->executeHook('before_display', ['products' => &$products]);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Wholesale Cannabis Menu</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .category {
            margin: 20px 0;
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .product-card {
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }
        .product-card h3 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .price {
            color: #27ae60;
            font-weight: bold;
        }
        .updated {
            color: #7f8c8d;
            font-size: 0.8em;
        }
        .product-image {
            max-width: 100%;
            height: auto;
            margin-bottom: 10px;
        }
    </style>
    <?php if (file_exists('custom.css')): ?>
        <link rel="stylesheet" href="custom.css">
    <?php endif; ?>
    <?php $plugin_manager->executeHook('header'); ?>
</head>
<body>
    <h1>Wholesale Cannabis Menu</h1>
    
    <?php
    $current_category = '';
    foreach ($products as $product) {
        if ($current_category !== $product['category_name']) {
            if ($current_category !== '') {
                echo '</div>'; // Close previous category grid
            }
            $current_category = $product['category_name'];
            echo "<div class='category'>";
            echo "<h2>" . htmlspecialchars($current_category) . "</h2>";
            echo "<div class='product-grid'>";
        }
    ?>
        <div class="product-card">
            <?php if ($product['image']): ?>
                <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
            <p>Strain: <?php echo htmlspecialchars($product['strain_type']); ?></p>
            <p>THC: <?php echo number_format($product['thc_content'], 1); ?>%</p>
            <p>CBD: <?php echo number_format($product['cbd_content'], 1); ?>%</p>
            <p class="price">$<?php echo number_format($product['price_per_gram'], 2); ?>/<?php echo getUnitAbbreviation($product['unit_type']); ?></p>
            <p>Available: <?php echo number_format($product['quantity_available'], 2); ?> <?php echo $product['unit_type']; ?></p>
            <?php if ($product['moq']): ?>
                <p>Minimum Order: <?php echo number_format($product['moq'], 2); ?> <?php echo $product['unit_type']; ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars($product['description']); ?></p>
            <p class="updated">Last Updated: <?php echo $product['last_updated']; ?></p>
            <?php $plugin_manager->executeHook('product_display', ['product' => $product]); ?>
        </div>
    <?php
    }
    if ($current_category !== '') {
        echo '</div></div>'; // Close last category and grid
    }
    ?>
    <?php $plugin_manager->executeHook('footer'); ?>
</body>
</html>