<?php
// Start session
session_start();

// Clear any existing session data if not properly logged in
if (!isset($_SESSION['pharmacy_user_id']) || empty($_SESSION['pharmacy_user_id'])) {
    session_unset();
    session_destroy();
    header("Location: pharmacy_login.php");
    exit();
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

require_once 'connect.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Pharmacy Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="css/pharmacy_dashboard.css" rel="stylesheet">
    <script src="js/dashboard.js" defer></script>
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(135deg, var(--bs-primary) 0%, var(--bs-secondary) 100%);

            color: white;
            transition: width 0.3s;
            z-index: 1000;
            padding-top: 60px;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s;
            padding: 20px;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        .nav-link {
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .nav-link:hover {
            background: rgba(255, 235, 235, 0.1);
            color: white;
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .nav-link span {
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar.collapsed .nav-link span {
            display: none;
        }

        .dashboard-block {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s;
        }

        .dashboard-block:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .dashboard-block i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: #1e3c72;
        }

        .section-content {
            display: none;
        }

        .section-content.active {
            display: block;
        }

        .toggle-sidebar {
            position: fixed;
            left: 10px;
            top: 10px;
            z-index: 1001;
            background: #1e3c72;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .header {
            background: white;
            padding: 15px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 999;
            transition: left 0.3s;
        }

        .header.expanded {
            left: var(--sidebar-collapsed-width);
        }

        .logo-container {
            text-align: center;
        }

        .logo-container img {
            max-height: 40px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logout-btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: #152a4f;
        }
        
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class='bx bx-menu'></i>
        </button>
        <div class="nav flex-column">
            <a class="nav-link mt-3" href="pharmacy_dashboard.php">
                <i class='bx bx-home'></i>
                <span>Home</span>
            </a>
            <a class="nav-link mt-3" href="#" data-section="make-bill">
                <i class='bx bx-receipt'></i>
                <span>Make a Bill</span>
            </a>
            <a class="nav-link mt-3" href="#" data-section="stock-entry">
                <i class='bx bx-plus-circle'></i>
                <span>Stock Entry</span>
            </a>
            <a class="nav-link mt-3" href="#" data-section="view-stock">
                <i class='bx bx-list-ul'></i>
                <span>View Stock List</span>
            </a>
            <a class="nav-link mt-3" href="#" data-section="suppliers">
                <i class='bx bx-group'></i>
                <span>Suppliers List</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <header class="header" id="header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-4">
                        <div class="user-info">
                            <i class='bx bx-user'></i>
                            <span><?php echo htmlspecialchars($_SESSION['pharmacy_username']); ?></span>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="logo-container">
                            <img src="img/klogo-.png" alt="Dr. Kiran">
                            <div>DR.KIRAN PHARMACY</div>
                        </div>
                    </div>
                    <div class="col-4 text-end">
                        <a href="pharmacy_logout.php" class="logout-btn">
                            <i class='bx bx-log-out'></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Blocks -->
        <div class="container mt-5 pt-5">
            <div class="row" id="dashboardBlocks">
                <div class="col-md-6 col-lg-6 ">
                    <div class="dashboard-block " onclick="billfunction()" id="makeBill">
                        <i class='bx bx-receipt'></i>
                        <h4>Make a Bill </h4>
                        <p>Create and manage bills</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6">
                    <div class="dashboard-block" onclick="stockentryfunction()" id="stockEntry">
                        <i class='bx bx-plus-circle'></i>
                        <h4>Stock Entry</h4>
                        <p>Add new stock items</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 ">
                    <div class="dashboard-block" onclick="viewstockfunction()" id="viewStock">
                        <i class='bx bx-list-ul'></i>
                        <h4>View Stock List</h4>
                        <p>View and manage stock</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6">
                    <div class="dashboard-block" onclick="supplierlistfunction()" id="supplierList">
                        <i class='bx bx-group'></i>
                        <h4>Suppliers List</h4>
                        <p>Manage suppliers</p>
                    </div>
                </div>
            </div>

            <!-- Section Contents -->
            <div id="sectionContents">
                <!-- Make a Bill Section -->
                <div class="section-content" id="make-bill-content">
                    <h2>Make a Bill</h2>
                    <!-- Add your bill creation form here -->
                </div>

                <!-- Stock Entry Section -->
                <div class="section-content" id="stock-entry-content">
                    <h2>Stock Entry</h2>
                    <!-- Add your stock entry form here -->
                </div>

                <!-- View Stock Section -->
                <div class="section-content" id="view-stock-content">
                    <h2>View Stock List</h2>
                    <!-- Add your stock list table here -->
                </div>
                <div class="section-content" id="supplier-add-content">

                </div>

                <!-- Suppliers Section -->
                <div class="section-content" id="suppliers-content">
                    <div class="card card-modern">
                        <div class="card-body">
                            <div class="datatables-header-footer-wrapper">
                                <div class="datatable-header">
                                    <div class="row align-items-center mb-3">
                                        <div class="col-12 col-lg-auto mb-3 mb-lg-0 btn btn-primary btn-md font-weight-semibold btn-py-2 px-4" data-section="supplier-add">
                                            <i class="bx bx-plus text-4 me-2"></i> Add New Supplier
                                        </div>
                                        <div class="col-8 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0">
                                            <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                                <label class="ws-nowrap me-3 mb-0">Filter By:</label>
                                                <select class="form-control select-style-1 filter-by" name="filter-by">
                                                    <option value="all" selected>All</option>
                                                    <option value="1">ID</option>
                                                    <option value="2">Name</option>
                                                    <option value="3">Phone</option>
                                                    <option value="4">E-mail</option>
                                                    <option value="5">Orders</option>
                                                    <option value="6">Total Amount</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-4 col-lg-auto ps-lg-1 mb-3 mb-lg-0">
                                            <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                                <label class="ws-nowrap me-3 mb-0">Show:</label>
                                                <select class="form-control select-style-1 results-per-page" name="results-per-page">
                                                    <option value="12" selected>12</option>
                                                    <option value="24">24</option>
                                                    <option value="36">36</option>
                                                    <option value="100">100</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-auto ps-lg-1">
                                            <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                                <div class="input-group">
                                                    <input type="text" class="search-term form-control" name="search-term" id="search-term" placeholder="Search Customer">
                                                    <button class="btn btn-default" type="submit"><i class="bx bx-search"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <table class="table table-ecommerce-simple table-striped mb-0" id="datatable-ecommerce-list" style="min-width: 750px;">

                                    <thead>
                                        <tr>
                                            <th width="32%">Name</th>
                                            <th width="18%">Phone</th>
                                            <th width="25%">Dl no</th>
                                            <th width="28%">GST NO</th>
                                            <th width="10%">SD Amout</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        // Fetch suppliers from the database
                                        $result = $conn->query("SELECT * FROM SUPPLIERS ORDER BY sd_amount DESC");
                                        while ($row = $result->fetch_assoc()):
                                        ?>
                                            <tr>
                                                <td><?= $row['name'] ?></td>
                                                <td><?= $row['phone'] ?></td>
                                                <td><?= $row['dl_no'] ?></td>
                                                <td><?= $row['gst'] ?></td>
                                                <td><?= $row['sd_amount'] ?></td>
                                                <td>
                                                    <a href="supplier_edit.php?id=<?= $row['id'] ?>">Edit</a>
                                                    <a href="supplier_delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
                                                </td>


                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>


                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>






    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
</body>

</html>