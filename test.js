document.querySelectorAll('.nav-link, .dashboard-block').forEach(element => {
    element.addEventListener('click', function() {
        const section = this.getAttribute('data-section');
        
        // Hide all sections
        document.querySelectorAll('.section-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Show selected section
        document.getElementById(section + '-content').classList.add('active');
        
        // Hide dashboard blocks
        document.getElementById('dashboardBlocks').style.display = 'none';
    });
});