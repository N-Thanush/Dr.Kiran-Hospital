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

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM SUPPLIERS WHERE ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <link href="css/pharmacy_dashboard.css" rel="stylesheet">
    <script src="js/dashboard.js" defer></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

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
            padding-right: 6%;
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
            cursor: grab;
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
            padding-left: 3%;
        }

        .logout-btn {
            background: #1e3c72;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .logout-btn:hover {
            background: rgb(250, 7, 7);
        }

        .addsupplier {
            display: none;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class='bx bx-menu '></i>
        </button>
        <div class="nav flex-column">
            <a class="nav-link mt-3" href="pharmacy_dashboard.php">
                <i class='bx bx-home '></i>
                <span>Home</span>
            </a>
            <div class="nav-link mt-3" onclick="billfunction()">
                <i class='bx bx-receipt '></i>
                <span>Make a Bill</span>
            </div>
            <div class="nav-link mt-3" onclick="stockentryfunction()">
                <i class='bx bx-plus-circle '></i>
                <span>Stock Entry</span>
            </div>
            <div class="nav-link mt-3" onclick="viewstockfunction()">
                <i class='bx bx-list-ul '></i>
                <span>View Stock List</span>
            </div>
            <div class="nav-link mt-3"
                onclick="supplierlistfunction();document.getElementById('sidebaraddsupplier').style.display='block'">
                <i class='bx bx-group '></i>
                <span>Suppliers List</span>

            </div>
            <div class="addsupplier nav-link mt-1" id="sidebaraddsupplier" onclick="addsupplier()">
                <i class="fa fa-plus ">
                </i>
                <span>Supplier</span>
            </div>

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
                            <i class=' bx bx-user'></i>
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
                        <a href="pharmacy_logout.php" class="logout-btn text-decoration-none"><i
                                class='bx bx-log-out'></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Blocks -->
        <div class="container mt-5 pt-5">
            <div class="row" id="dashboardBlocks">
                <div class="col-md-6 col-lg-6 ">
                    <div class="dashboard-block " onclick="billfunction()">
                        <i class='bx bx-receipt'></i>
                        <h4>Make a Bill </h4>
                        <p>Create and manage bills</p>
                    </div>
                </div>
                <div class=" col-md-6 col-lg-6">
                    <div class="dashboard-block" onclick="stockentryfunction()">
                        <i class='bx bx-plus-circle'></i>
                        <h4>Stock Entry</h4>
                        <p>Add new stock items</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 ">
                    <div class="dashboard-block" onclick="viewstockfunction()">
                        <i class='bx bx-list-ul'></i>
                        <h4>View Stock List</h4>
                        <p>View and manage stock</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6">
                    <div class="dashboard-block" onclick="supplierlistfunction()">
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
                    <!-- start: page -->
                    <form class="ecommerce-form action-buttons-fixed mt-3" action="#" method="post">
                        <div class="row">
                            <div class="col">
                                <section class="card card-modern card-big-info">
                                    <div class="card-body">
                                        <div class="row">

                                            <div class="col-lg-3-5 col-xl-4-5">
                                                <div class="form-group row align-items-center pb-3">
                                                    <label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">SD
                                                        Amount</label>
                                                    <div class="col-lg-7 col-xl-6">
                                                        <input type="text" class="form-control form-control-modern"
                                                            name="sd-amount" value="" required />
                                                    </div>
                                                </div>
                                                <div class="form-group row align-items-center pb-3">
                                                    <label
                                                        class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">Name</label>
                                                    <div class="col-lg-7 col-xl-6">
                                                        <input type="text" class="form-control form-control-modern"
                                                            name="Name" value="" required />
                                                    </div>
                                                </div>
                                                <div class="form-group row align-items-center pb-3">
                                                    <label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">DL
                                                        No.</label>
                                                    <div class="col-lg-7 col-xl-6">
                                                        <input type="text" class="form-control form-control-modern"
                                                            name="dl-no" value="" />
                                                    </div>
                                                </div>
                                                <div class="form-group row align-items-center pb-3">
                                                    <label
                                                        class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">GSTIN</label>
                                                    <div class="col-lg-7 col-xl-6">
                                                        <input type="text" class="form-control form-control-modern"
                                                            name="gst" value="" required />
                                                    </div>
                                                </div>
                                                <div class="form-group row align-items-center pb-3">
                                                    <label
                                                        class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">FSSAI</label>
                                                    <div class="col-lg-7 col-xl-6">
                                                        <input type="text" class="form-control form-control-modern"
                                                            name="fassai" value="" />
                                                    </div>
                                                </div>
                                                <div class="form-group row align-items-center pb-3">
                                                    <label
                                                        class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">Phone</label>
                                                    <div class="col-lg-7 col-xl-6">
                                                        <input type="text" class="form-control form-control-modern"
                                                            name="phone" value="" required />
                                                    </div>
                                                </div>
                                                <div class="row action-buttons mt-3" style="padding-left: 40%;">
                                                    <div class="col-12 col-md-auto mx-5 mb-3 mb-md-0">
                                                        <button type="submit"
                                                            class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1"
                                                            data-loading-text="Loading..." name="submit">
                                                            <i class="bx bx-save text-4 me-2"></i> Save Supplier
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </div>
                    </form>
                    <?php
                    if (isset($_POST['submit'])) {
                        $sd_amount = $_POST['sd-amount'];
                        $name = $_POST['Name'];
                        $dl_no = $_POST['dl-no'];
                        $gst = $_POST['gst'];
                        $fssai = $_POST['fassai'];
                        $phone = $_POST['phone'];
                        $form_success = true;

                        $stmt = $conn->prepare("INSERT INTO SUPPLIERS (sd_amount, name, dl_no, gst, fssai, phone) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("dsssss", $sd_amount, $name, $dl_no, $gst, $fssai, $phone);
                        $stmt->execute();

                        if ($form_success) {
                            echo " <script>
							window.opener.location.href = 'pharmacy_dashboard.php';
							window.close();
							alert('Supplier Added Successfully');
						</script>";
                        } else {
                            echo "error:" . $conn->error;
                        }
                    }
                    ?>
                    <!-- end: page -->
                </div>
                <!-- Suppliers Section -->
                <div class="section-content" id="suppliers-content">
                    <div class="card card-modern">
                        <div class="card-body">
                            <div class="datatables-header-footer-wrapper">
                                <div class="datatable-header">

                                    <div class="row align-items-center mb-3">
                                        <div class="col-8 col-lg-auto  ml-auto mb-3 mb-lg-0 "
                                            style="padding-right: 15%;">
                                            <div class="d-flex align-items-lg-center flex-column flex-lg-row">
                                                <label class="ws-nowrap me-3 mb-0">Filter By:</label>
                                                <select class="form-control select-style-1 filter-by" name="filter-by">
                                                    <option value="all" selected>All</option>
                                                    <option value="1">Name</option>
                                                    <option value="3">Phone</option>
                                                    <option value="2">Dl No</option>
                                                    <option value="4">GST No</option>
                                                    <option value="6">SD Amount</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-4 col-lg-auto ps-lg-1 mb-3 mb-lg-0" style="padding-right: 15%;">
                                            <div class=" d-flex align-items-lg-center flex-column flex-lg-row">
                                                <label class="ws-nowrap me-3 mb-0">Show:</label>
                                                <select class="form-control select-style-1 results-per-page"
                                                    name="results-per-page">
                                                    <option value="12" selected>12</option>
                                                    <option value="24">24</option>
                                                    <option value="36">36</option>
                                                    <option value="100">100</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-auto ps-lg-1">
                                            <div class="search search-style-1 search-style-1-lg mx-lg-auto"
                                                style="padding-left: 15%;">
                                                <div class="input-group">
                                                    <input type="text" class="search-term form-control"
                                                        name="search-term" id="search-term"
                                                        placeholder="Search Customer">
                                                    <button class="btn btn-default" type="submit"><i
                                                            class="bx bx-search"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <table class="table table-ecommerce-simple table-striped mb-0"
                                    id="datatable-ecommerce-list" style="min-width: 750px;">

                                    <thead>
                                        <tr>
                                            <th width="25%">Name</th>
                                            <th width="10%">Phone</th>
                                            <th width="10%">Dl no</th>
                                            <th width="20%">GST NO</th>
                                            <th width="10%">SD Amout</th>
                                            <th width="15%">E & D</th>
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
                                                <!-- <td>
                                                    <a href="pharmacy_dashboard.php?delete=<?= $row['id'] ?>"
                                                        class="btn btn-sm btn-danger"
                                                        onclick="return confirm('Are you sure you want to delete this supplier?');">
                                                        🗑️
                                                    </a>
                                                </td> -->



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