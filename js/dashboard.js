    // Toggle sidebar
    document.getElementById('toggleSidebar').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const header = document.getElementById('header');

        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        header.classList.toggle('expanded');
    });

    // Handle section switching
    // Generic function to switch active section
    function switchSection(activeSectionId) {
        document.getElementById('dashboardBlocks').style.display = 'none';

        const sections = [
            'stock-entry-content',
            'view-stock-content',
            'suppliers-content',
            'make-bill-content',
            'supplier-add-content',
        ];

        sections.forEach(sectionId => {
            const element = document.getElementById(sectionId);
            element.classList.toggle('active', sectionId === activeSectionId);
        });
    }

    // Specific functions
    function billfunction() {
        switchSection('make-bill-content');
    }

    function stockentryfunction() {
        switchSection('stock-entry-content');
    }

    function viewstockfunction() {
        switchSection('view-stock-content');
    }

    function supplierlistfunction() {
        switchSection('suppliers-content');

    }
    function addsupplier() {
        switchSection('supplier-add-content');
    }


    // Handle back to dashboard
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('back-to-dashboard')) {
            document.getElementById('dashboardBlocks').style.display = 'flex';
            document.querySelectorAll('.section-content').forEach(content => {
                content.classList.remove('active');
            });
        }
    });

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
            data: Object.fromEntries(formData.entries()),

            success: function(response) {
                if (response === 'success') {
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

    // function viewAllStock() {
    //     $.ajax({
    //         url: 'pharmacy_actions.php',
    //         type: 'POST',
    //         data: {
    //             action: 'view_all'
    //         },
    //         success: function(response) {
    //             document.getElementById('stockList').innerHTML = response;
    //         }
    //     });
    // }

    // // Load all stock on page load
    // document.addEventListener('DOMContentLoaded', function() {
    //     viewAllStock();
    // });

    window.addEventListener("pageshow", function(event) {
        if (event.persisted || performance.getEntriesByType("navigation")[0].type === "back_forward") {
            window.location.reload();
        }
    });
