<footer class="mt-5 pt-4 border-top text-center text-muted small">
    <p>&copy;
        <?php echo date('Y'); ?> Hana Project - Online Check Sheet System. All rights reserved.
    </p>
</footer>
</main>

<!-- Scripts -->
<!-- Scripts -->
<script src="assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
<script src="assets/libs/jquery/jquery.min.js"></script>
<script>
    // Sidebar Toggle Logic (Desktop & Mobile)
    $('#sidebarToggle').on('click', function () {
        if (window.innerWidth > 992) {
            $('#sidebar').toggleClass('collapsed');
        } else {
            $('#sidebar').toggleClass('active');
        }
    });

    // Active Link Highlight
    const currentLocation = location.href;
    const menuItem = document.querySelectorAll('.nav-link');
    const menuLength = menuItem.length;
    for (let i = 0; i < menuLength; i++) {
        if (menuItem[i].href === currentLocation) {
            menuItem.forEach(item => item.classList.remove('active'));
            menuItem[i].classList.add('active');

            // Update Page Title
            const title = menuItem[i].innerText.trim();
            document.getElementById('page-title').innerText = title;
        }
    }

    // Global SweetAlert Notifications
    const urlParams = new URLSearchParams(window.location.search);

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    if (urlParams.has('success')) {
        Toast.fire({
            icon: 'success',
            title: 'Action completed successfully!'
        });
    }

    if (urlParams.has('error')) {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Something went wrong! Please try again.',
            confirmButtonColor: '#4f46e5'
        });
    }

    if (urlParams.has('deleted')) {
        Toast.fire({
            icon: 'warning',
            title: 'Record deleted successfully.'
        });
    }

    // Common function for delete confirmation
    function confirmDelete(url) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
</script>
</body>

</html>