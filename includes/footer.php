<footer class="mt-5 pt-4 border-top text-center text-muted small">
    <p>&copy;
        <?php echo date('Y'); ?> Hana Project - Online Check Sheet System. All rights reserved.
    </p>
</footer>
</main>

<!-- Scripts -->
<script src="<?php echo BASE_URL; ?>assets/libs/bootstrap/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>assets/libs/jquery/jquery.min.js"></script>
<script>
    // Sidebar Toggle Logic (Desktop & Mobile)
    $('#sidebarToggle').on('click', function () {
        if (window.innerWidth > 992) {
            $('#sidebar').toggleClass('collapsed');
        } else {
            $('#sidebar').toggleClass('active');
        }
    });

    // Active Link Highlight - Use pathname to ignore query params
    const currentPath = location.pathname;
    const menuItem = document.querySelectorAll('.nav-link');
    menuItem.forEach(item => {
        // Extract pathname from link href
        const linkPath = new URL(item.href, location.origin).pathname;

        // Check if current path ends with or matches the link path
        if (currentPath === linkPath || currentPath.endsWith('/' + linkPath.split('/').pop())) {
            menuItem.forEach(m => m.classList.remove('active'));
            item.classList.add('active');

            // Update Page Title
            const title = item.innerText.trim();
            const titleEl = document.getElementById('page-title');
            if (titleEl) titleEl.innerText = title;
        }
    });

    // Global SweetAlert Notifications
    $(document).ready(function () {
        const urlParams = new URLSearchParams(window.location.search);

        // Check if Swal is defined
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 is not loaded!');
            return;
        }

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
            console.log('Showing success toast');
            Toast.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ!'
            });
            // Clean URL after showing toast
            setTimeout(() => {
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 100);
        }

        if (urlParams.has('error')) {
            const errorMsg = urlParams.get('error');
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: errorMsg && errorMsg !== '1' ? decodeURIComponent(errorMsg) : 'กรุณาลองใหม่อีกครั้ง',
                confirmButtonColor: '#4f46e5'
            });
            // Clean URL after showing swal
            setTimeout(() => {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }, 100);
        }

        if (urlParams.has('deleted')) {
            Toast.fire({
                icon: 'warning',
                title: 'ลบข้อมูลสำเร็จ!'
            });
            // Clean URL after showing toast
            setTimeout(() => {
                window.history.replaceState({}, document.title, window.location.pathname);
            }, 100);
        }
    });

    // Common function for delete confirmation
    function confirmDelete(url) {
        Swal.fire({
            title: 'ยืนยันการลบ?',
            text: "คุณจะไม่สามารถกู้คืนข้อมูลนี้ได้!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'ใช่, ลบเลย!',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
</script>
</body>

</html>