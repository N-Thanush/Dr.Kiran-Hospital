<?php
session_start();
if (!isset($_SESSION['pharmacy_user_id'])) {
    exit('Unauthorized');
}

require_once 'connect.php';

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'add_stock':
        $product_name = $_POST['product_name'];
        $product_type = $_POST['product_type'];
        $batch_no = $_POST['batch_no'];
        $quantity = $_POST['quantity'];
        $mrp = $_POST['mrp'];
        $rate = $_POST['rate'];
        $gst = $_POST['gst'];
        $expiry_date = $_POST['expiry_date'];
        
        // Calculate SGST and CGST (half of GST each)
        $sgst = ($rate * $gst / 200);
        $cgst = $sgst;
        $total_amount = $rate + $sgst + $cgst;
        
        $sql = "INSERT INTO medicine_stock (product_name, product_type, batch_no, quantity, mrp, rate, gst, sgst, cgst, total_amount, expiry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssiddddddss", $product_name, $product_type, $batch_no, $quantity, $mrp, $rate, $gst, $sgst, $cgst, $total_amount, $expiry_date);
        
        echo $stmt->execute() ? 'success' : 'error';
        break;
        
    case 'search':
        $term = $_POST['term'] ?? '';
        $type = $_POST['type'] ?? 'all';
        
        $sql = "SELECT * FROM medicine_stock WHERE 1=1";
        if (!empty($term)) {
            $term = "%$term%";
            $sql .= " AND (product_name LIKE ? OR batch_no LIKE ?)";
        }
        if ($type !== 'all') {
            $sql .= " AND product_type = ?";
        }
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($term) && $type !== 'all') {
            $stmt->bind_param("sss", $term, $term, $type);
        } elseif (!empty($term)) {
            $stmt->bind_param("ss", $term, $term);
        } elseif ($type !== 'all') {
            $stmt->bind_param("s", $type);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        outputStockTable($result);
        break;
        
    case 'view_all':
        $result = $conn->query("SELECT * FROM medicine_stock ORDER BY created_at DESC");
        outputStockTable($result);
        break;
}

function outputStockTable($result) {
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-info">No records found</div>';
        return;
    }
    
    echo '<table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Type</th>
                    <th>Batch No</th>
                    <th>Quantity</th>
                    <th>MRP</th>
                    <th>Rate</th>
                    <th>GST %</th>
                    <th>SGST</th>
                    <th>CGST</th>
                    <th>Total</th>
                    <th>Expiry Date</th>
                </tr>
            </thead>
            <tbody>';
    
    while ($row = $result->fetch_assoc()) {
        $expiryDate = new DateTime($row['expiry_date']);
        $today = new DateTime();
        $expiryClass = '';
        
        if ($today > $expiryDate) {
            $expiryClass = 'table-danger';
        } elseif ($today->diff($expiryDate)->days < 30) {
            $expiryClass = 'table-warning';
        }
        
        echo "<tr class='$expiryClass'>
                <td>{$row['product_name']}</td>
                <td>{$row['product_type']}</td>
                <td>{$row['batch_no']}</td>
                <td>{$row['quantity']}</td>
                <td>₹{$row['mrp']}</td>
                <td>₹{$row['rate']}</td>
                <td>{$row['gst']}%</td>
                <td>₹{$row['sgst']}</td>
                <td>₹{$row['cgst']}</td>
                <td>₹{$row['total_amount']}</td>
                <td>{$row['expiry_date']}</td>
              </tr>";
    }
    
    echo '</tbody></table>';
} 