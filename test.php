<?php
session_start();
if (!isset($_SESSION['pharmacy_user_id'])) {
    header("Location: pharmacy_login.php");
    exit();
}
require_once 'connect.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="css/pharmacy_dashboard.css" rel="stylesheet">
    <link href="css/index.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/klogo-.png" alt="Dr. Kiran" class="d-inline-block align-top">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <div class="d-flex align-items-center gap-2">
                    <form method="post" action="pharmacy_login.php" onsubmit="return true;">
                        <button type="submit" class="btn emergency-btn">
                            Log Out
                        </button>
                    </form>
                </div>
        </div>
    </nav>

    <div class="dashboard-container">
        

        <!-- Search Box -->
        <div class="search-box">
            <div class="row g-3">
                <div class="col-12 col-md-8">
                    <input type="text" id="searchInput" class="form-control" placeholder="Search medicines...">
                </div>
                <div class="col-12 col-md-2">
                    <select id="searchType" class="form-select">
                        <option value="all">All Types</option>
                        <option value="injections">Injections</option>
                        <option value="syrup">Syrup</option>
                        <option value="syringes">Syringes</option>
                        <option value="tablets">Tablets</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100" onclick="searchMedicines()">Search</button>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4 action-buttons">
            <div class="col-12 col-md-6">
                <button class="btn btn-primary w-100 mb-2 mb-md-0" data-bs-toggle="modal" data-bs-target="#stockEntryModal">
                    <i class="bx bx-plus"></i> New Stock Entry
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button class="btn btn-secondary w-100" onclick="viewAllStock()">
                    <i class="bx bx-list-ul"></i> View All Stock
                </button>
            </div>
        </div>

        <!-- Stock List -->
        <div id="stockList" class="table-responsive">
            <!-- Stock data will be loaded here -->
        </div>
    </div>

    <!-- Stock Entry Modal -->
    <div class="modal fade" id="stockEntryModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Stock Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="stockEntryForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name</label>
                                    <input type="text" class="form-control" name="product_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Type</label>
                                    <select class="form-select" name="product_type" required>
                                        <option value="">Select Type</option>
                                        <option value="injections">Injections</option>
                                        <option value="syrup">Syrup</option>
                                        <option value="syringes">Syringes</option>
                                        <option value="tablets">Tablets</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Batch No</label>
                                    <input type="text" class="form-control" name="batch_no" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="quantity" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">MRP</label>
                                    <input type="number" step="0.01" class="form-control" name="mrp" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Rate</label>
                                    <input type="number" step="0.01" class="form-control" name="rate" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">GST %</label>
                                    <input type="number" step="0.01" class="form-control" name="gst" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" class="form-control" name="expiry_date" required>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitStockEntry()">Save Entry</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function searchMedicines() {
            const searchTerm = document.getElementById('searchInput').value;
            const searchType = document.getElementById('searchType').value;
            
            $.ajax({
                url: 'pharmacy_actions.php',
                type: 'POST',
                data: {
                    action: 'search',
                    term: searchTerm,
                    type: searchType
                },
                success: function(response) {
                    document.getElementById('stockList').innerHTML = response;
                }
            });
        }

        function submitStockEntry() {
            const formData = new FormData(document.getElementById('stockEntryForm'));
            formData.append('action', 'add_stock');
            
            $.ajax({
                url: 'pharmacy_actions.php',
                type: 'POST',
                data: Object.fromEntries(formData),
                success: function(response) {
                    if(response === 'success') {
                        alert('Stock entry added successfully');
                        $('#stockEntryModal').modal('hide');
                        document.getElementById('stockEntryForm').reset();
                        viewAllStock();
                    } else {
                        alert('Error adding stock entry');
                    }
                }
            });
        }

        function viewAllStock() {
            $.ajax({
                url: 'pharmacy_actions.php',
                type: 'POST',
                data: {
                    action: 'view_all'
                },
                success: function(response) {
                    document.getElementById('stockList').innerHTML = response;
                }
            });
        }

        // Load all stock on page load
        document.addEventListener('DOMContentLoaded', function() {
            viewAllStock();
        });
    </script>
</body>
</html> 