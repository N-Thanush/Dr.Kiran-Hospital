<php? include 'connect.php'; ?>
<!doctype html>
<html class="modern fixed has-top-menu has-left-sidebar-half">
	<head>

		<!-- Basic -->
		<meta charset="UTF-8">

		<title>Customer Name | Porto Admin - Responsive HTML5 Template</title>

		<meta name="keywords" content="HTML5 Admin Template" />
		<meta name="description" content="Porto Admin - Responsive HTML5 Template">
		<meta name="author" content="okler.net">

		<!-- Mobile Metas -->
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

		<!-- Web Fonts  -->
		<link href="https://fonts.googleapis.com/css?family=Poppins:100,300,400,600,700,800,900" rel="stylesheet" type="text/css">

		<!-- Vendor CSS -->
		<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.css" />
		<link rel="stylesheet" href="vendor/animate/animate.compat.css">
		<link rel="stylesheet" href="vendor/font-awesome/css/all.min.css" />
		<link rel="stylesheet" href="vendor/boxicons/css/boxicons.min.css" />
		<link rel="stylesheet" href="vendor/magnific-popup/magnific-popup.css" />
		<link rel="stylesheet" href="vendor/bootstrap-datepicker/css/bootstrap-datepicker3.css" />
		<link rel="stylesheet" href="vendor/pnotify/pnotify.custom.css" />

		<!-- Theme CSS -->
		<link rel="stylesheet" href="css/theme.css" />

		<!-- Theme Layout -->
		<link rel="stylesheet" href="css/layouts/modern.css" />

		<!-- Skin CSS -->
		<link rel="stylesheet" href="css/skins/default.css" />

		<!-- Theme Custom CSS -->
		<link rel="stylesheet" href="css/custom.css">

		<!-- Head Libs -->
		<script src="vendor/modernizr/modernizr.js"></script>

	</head>
	<body>
		<section class="body">

			<!-- start: header -->
			
			<!-- end: header -->

			<div class="inner-wrapper">
				<!-- start: sidebar -->
				<aside id="sidebar-left" class="sidebar-left">

				    <div class="sidebar-header">
				        <div class="sidebar-toggle d-none d-md-flex" data-toggle-class="sidebar-left-collapsed" data-target="html" data-fire-event="sidebar-left-toggle">
				            <i class="fas fa-bars" aria-label="Toggle sidebar"></i>
				        </div>
				    </div>



					<!-- start: page -->
					<form class="ecommerce-form action-buttons-fixed mt-3" action="#" method="post">
						<div class="row">
							<div class="col">
								<section class="card card-modern card-big-info">
									<div class="card-body">
										<div class="row">
											
											<div class="col-lg-3-5 col-xl-4-5">
												<div class="form-group row align-items-center pb-3">
													<label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">SD Amount</label>
													<div class="col-lg-7 col-xl-6">
														<input type="text" class="form-control form-control-modern" name="sd-amount" value="" required />
													</div>
												</div>
												<div class="form-group row align-items-center pb-3">
													<label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">Name</label>
													<div class="col-lg-7 col-xl-6">
														<input type="text" class="form-control form-control-modern" name="Name" value="" required />
													</div>
												</div>
												<div class="form-group row align-items-center pb-3">
													<label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">DL No.</label>
													<div class="col-lg-7 col-xl-6">
														<input type="text" class="form-control form-control-modern" name="dl-no" value="" />
													</div>
												</div>
												<div class="form-group row align-items-center pb-3">
													<label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">GSTIN</label>
													<div class="col-lg-7 col-xl-6">
														<input type="text" class="form-control form-control-modern" name="gst" value="" required />
													</div>
												</div>
												<div class="form-group row align-items-center pb-3">
													<label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">FSSAI</label>
													<div class="col-lg-7 col-xl-6">
														<input type="text" class="form-control form-control-modern" name="fassai" value="" />
													</div>
												</div>
												<div class="form-group row align-items-center pb-3">
													<label class="col-lg-5 col-xl-3 control-label text-lg-end mb-0">Phone</label>
													<div class="col-lg-7 col-xl-6">
														<input type="text" class="form-control form-control-modern" name="phone" value="" required />
													</div>
												</div>
												<div class="row action-buttons mt-3">
													<div class="col-12 col-md-auto mx-5 ">
														<button type="submit" class="submit-button btn btn-primary btn-px-4 py-3 d-flex align-items-center font-weight-semibold line-height-1" data-loading-text="Loading..." name="submit">
															<i class="bx bx-save text-4 me-2"></i> Save Customer
														</button>
													</div>
													<div class="col-12 col-md-auto align-items-right d-flex ms-auto mx-5">
														<a href="#" class="cancel-button btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3">Cancel</a>
													</div>
													
												</div>
											</div>
										</div>
									</div>
								</section>
							</div>
						</div>
					</form>
					<php? 
					if(isset($_POST['submit'])){
						$sd_amount = $_POST['sd-amount'];
						$name = $_POST['Name'];
						$dl_no = $_POST['dl-no'];
						$gst = $_POST['gst'];
						$fssai = $_POST['fassai'];
						$phone = $_POST['phone'];
						$form_success = true;
					}
					$conn->query("INSERT INTO SUPPLIERS (sd_amount, name,dl_no,gst, fssai, phone) VALUES ('$sd-amount','$name','$dl_no', '$gst', '$fssai', '$phone') ");

					if($form_success){
						echo " <script>
							window.opener.location.href = 'pharmacy_dashboard.php';
							window.close();
							alert('Supplier Added Successfully');
							</script>";
					
					}
					?>
					<!-- end: page -->
				</section>
			</div>

			

		</section>

		<!-- Vendor -->
		<script src="vendor/jquery/jquery.js"></script>
		<script src="vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
		<script src="vendor/popper/umd/popper.min.js"></script>
		<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
		<script src="vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
		<script src="vendor/common/common.js"></script>
		<script src="vendor/nanoscroller/nanoscroller.js"></script>
		<script src="vendor/magnific-popup/jquery.magnific-popup.js"></script>
		<script src="vendor/jquery-placeholder/jquery.placeholder.js"></script>

		<!-- Specific Page Vendor -->
		<script src="vendor/jquery-validation/jquery.validate.js"></script>
		<script src="vendor/pnotify/pnotify.custom.js"></script>

		<!-- Theme Base, Components and Settings -->
		<script src="js/theme.js"></script>

		<!-- Theme Custom -->
		<script src="js/custom.js"></script>

		<!-- Theme Initialization Files -->
		<script src="js/theme.init.js"></script>

		<!-- Examples -->
		<script src="js/examples/examples.header.menu.js"></script>
		<script src="js/examples/examples.ecommerce.form.js"></script>

	</body>
</html>