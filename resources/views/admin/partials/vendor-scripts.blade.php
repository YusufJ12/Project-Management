<!-- JAVASCRIPT -->
<script src="{{ asset('admin-assets/libs/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('admin-assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('admin-assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('admin-assets/libs/sweetalert2/sweetalert2.all.min.js') }}"></script>
<script src="{{ asset('admin-assets/js/scroll-top.init.js') }}"></script>
<script src="{{ asset('admin-assets/js/sidebar-active.js') }}"></script>
<script src="{{ asset('admin-assets/js/toast-utility.js') }}"></script>

@if (session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.toastSuccess(@json(session('success')));
    });
</script>
@endif
@if (session('error'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.toastError(@json(session('error')));
    });
</script>
@endif
@if (session('warning'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.toastWarning(@json(session('warning')));
    });
</script>
@endif
@if (session('info'))
<script>
    document.addEventListener('DOMContentLoaded', function() {
        window.toastInfo(@json(session('info')));
    });
</script>
@endif

