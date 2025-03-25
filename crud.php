<?php
// crud.php
// Add at the top of crud.php
header('Content-Type: application/json');
$servername = "localhost";
$username = "mk";
$password = "password";
$dbname = "xlspdf";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
$UPLOAD_DIR = 'img/'; // Set upload directory

// Create upload directory if it doesn't exist
if (!file_exists($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}
//$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$action = $_POST['action'] ?? $_GET['action'] ?? '';
switch ($action) {
    case 'read':
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 5;
        $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

        $offset = ($page - 1) * $per_page;

        // Build the WHERE clause for the search
        $whereClause = '';
        $params = [];
        if ($searchTerm) {
            $whereClause = " WHERE codigo LIKE :search OR descricao LIKE :search";
            $params[':search'] = '%' . $searchTerm . '%';
        }

        // Get total number of products matching the search
        $stmt = $conn->prepare("SELECT COUNT(*) FROM products" . $whereClause);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_products = $stmt->fetchColumn();

        // Get products for the current page matching the search
        $stmt = $conn->prepare("SELECT * FROM products" . $whereClause . " LIMIT :per_page OFFSET :offset");

        $stmt->bindParam(':per_page', $per_page, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['products' => $products, 'total_products' => $total_products]);
           


    // Rest of your existing pagination code...
    break;      


       case 'editItem' :
           if (isset($_GET['id'])) {
        try {
            $id = intval($_GET['id']);
             if (!empty($_FILES['image']['name'])) {
            $uploadDir = 'img/';
            $uploadFile = $uploadDir . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $imagePath = $uploadFile;
            }
        }
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
                exit;
            }
            
            echo json_encode($product);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
            break; 
     case 'create':
        $codigo = $_POST['codigo'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $imagePath = '';

        // Handle file upload
        if (!empty($_FILES['image']['name'])) {
            $uploadDir = 'img/';
            $uploadFile = $uploadDir . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                $imagePath = $uploadFile;
            }
        }

        try {
            $stmt = $conn->prepare("INSERT INTO products (codigo, descricao, image) VALUES (:codigo, :descricao, :image)");
            $stmt->execute([
                ':codigo' => $codigo,
                ':descricao' => $descricao,
                ':image' => $imagePath
            ]);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Create failed: ' . $e->getMessage()]);
        }
        break;

    case 'update':
        $id = $_POST['id'] ?? '';
        $codigo = $_POST['codigo'] ?? '';
        $descricao = $_POST['descricao'] ?? '';
        $imagePath = '';
         

         // Handle file upload if new image is provided
    if (!empty($_FILES['image']['name'])) {
        if (empty($codigo)) {
            http_response_code(400);
            echo json_encode(['error' => 'Product code is required when uploading an image.']);
            exit;
        }
        $uploadDir = 'img/';
        $originalName = $_FILES['image']['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $newFilename = $codigo . '.' . $extension;
        $uploadFile = $uploadDir . $newFilename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            $imagePath = $uploadFile;
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to upload image.']);
            exit;
        }
    }
        
        try {
            // Update image only if new file was uploaded
            $sql = "UPDATE products SET codigo = :codigo, descricao = :descricao" . 
                  ($imagePath ? ", image = :image" : "") . 
                  " WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $params = [
                ':codigo' => $codigo,
                ':descricao' => $descricao,
                ':id' => $id
            ];
            

            if ($imagePath) {
                $params[':image'] = $imagePath;
            }

            $stmt->execute($params);
            echo json_encode(['status' => 'success']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
        }
        break;

    case 'updateField':
        $id = $_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];

        // Sanitize $field! (See important notes in previous response)
        $allowedFields = ['codigo', 'descricao'];
        if (!in_array($field, $allowedFields)) {
            http_response_code(400); // Bad Request
            echo "Invalid field: " . htmlspecialchars($field);
            exit;
        }

        try {
            $stmt = $conn->prepare("UPDATE products SET $field = :value WHERE id = :id");
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            echo "success";  // Send a success response
        } catch (PDOException $e) {
            http_response_code(500); // Internal Server Error
            echo "Error updating field: " . $e->getMessage(); // Send error message
        }
        break;

   

     case 'delete':
        // Handle delete operation
        $id = $_GET['id'];

        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid product ID']);
            exit;
        }

        try {
            $stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            echo json_encode([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Delete failed: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

$conn = null;
?>