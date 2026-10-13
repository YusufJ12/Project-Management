/**
 * Global Toast & Alert Notification Utility
 * Uses SweetAlert2 with premium design matching template
 * Button Order: Cancel (LEFT) - Confirm (RIGHT)
 */

// Toast position configuration - Top Right, Premium Design
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

// Global toast functions (for quick notifications)
window.showToast = {
    success: function (message, title = 'Success!') {
        Toast.fire({ icon: 'success', title: title, text: message });
    },
    error: function (message, title = 'Error!') {
        Toast.fire({ icon: 'error', title: title, text: message });
    },
    warning: function (message, title = 'Warning!') {
        Toast.fire({ icon: 'warning', title: title, text: message });
    },
    info: function (message, title = 'Info') {
        Toast.fire({ icon: 'info', title: title, text: message });
    }
};

// Shorthand toast functions
window.toastSuccess = (msg) => showToast.success(msg);
window.toastError = (msg) => showToast.error(msg);
window.toastWarning = (msg) => showToast.warning(msg);
window.toastInfo = (msg) => showToast.info(msg);

/**
 * Premium Confirm Dialog - Matches template design
 * Button Order: Cancel (LEFT) - Confirm (RIGHT)
 */
window.showConfirm = function (options) {
    return Swal.fire({
        title: options.title || 'Konfirmasi',
        html: options.html || options.text || 'Apakah Anda yakin?',
        icon: options.icon || 'warning',
        showCancelButton: true,
        confirmButtonText: options.confirmText || '<i class="bi bi-trash me-1"></i> Ya, Hapus',
        cancelButtonText: options.cancelText || '<i class="bi bi-x me-1"></i> Batal',
        reverseButtons: false,
        buttonsStyling: false,
        customClass: {
            popup: 'swal2-popup-custom',
            actions: 'd-flex gap-2',
            confirmButton: options.confirmClass || 'btn btn-danger',
            cancelButton: 'btn btn-secondary'
        }
    });
};

/**
 * Delete Confirmation - Standard delete dialog
 * @param {string} itemName - Name of item to delete
 * @param {function} onConfirm - Callback when confirmed
 */
window.confirmDelete = function (itemName, onConfirm) {
    showConfirm({
        title: '<i class="bi bi-exclamation-triangle text-warning me-2"></i> Confirm Delete',
        html: `Are you sure you want to delete <strong>${itemName}</strong>?<br><small class="text-danger">This action cannot be undone!</small>`,
        icon: null,
        confirmText: '<i class="bi bi-trash me-1"></i> Delete',
        cancelText: '<i class="bi bi-x me-1"></i> Cancel',
        confirmClass: 'btn btn-danger'
    }).then((result) => {
        if (result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
};

/**
 * General Action Confirmation
 * @param {object} options - { title, text/html, confirmText, cancelText, icon }
 * @param {function} onConfirm - Callback when confirmed
 */
window.confirmAction = function (options, onConfirm) {
    showConfirm({
        title: options.title || 'Confirm Action',
        html: options.html || options.text || 'Are you sure?',
        icon: options.icon || 'question',
        confirmText: options.confirmText || '<i class="bi bi-check me-1"></i> Yes',
        cancelText: options.cancelText || '<i class="bi bi-x me-1"></i> Cancel',
        confirmClass: options.confirmClass || 'btn btn-primary'
    }).then((result) => {
        if (result.isConfirmed && typeof onConfirm === 'function') {
            onConfirm();
        }
    });
};

// Premium Success Alert - with icon and auto close
window.showSuccess = function (message, title = 'Success!') {
    return Swal.fire({
        icon: 'success',
        title: title,
        text: message,
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
    });
};

// Premium Error Alert
window.showError = function (message, title = 'Error!') {
    return Swal.fire({
        icon: 'error',
        title: title,
        text: message,
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-danger' }
    });
};

// Premium Warning Alert
window.showWarning = function (message, title = 'Warning!') {
    return Swal.fire({
        icon: 'warning',
        title: title,
        text: message,
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-warning' }
    });
};

// Premium Info Alert
window.showInfo = function (message, title = 'Info') {
    return Swal.fire({
        icon: 'info',
        title: title,
        text: message,
        buttonsStyling: false,
        customClass: { confirmButton: 'btn btn-info' }
    });
};

// Delegated handler for [data-confirm]
document.addEventListener('submit', function (event) {
    const form = event.target;
    const message = form.dataset.confirm;
    if (!message || form.dataset.confirmed === 'true') {
        return;
    }

    event.preventDefault();
    window.showConfirm({
        title: form.dataset.confirmTitle || 'Konfirmasi',
        html: message,
        icon: form.dataset.confirmIcon || 'warning',
        confirmText: form.dataset.confirmBtn || '<i class="bi bi-trash me-1"></i> Ya, Lanjutkan',
        confirmClass: form.dataset.confirmClass || 'btn btn-danger'
    }).then((result) => {
        if (result.isConfirmed) {
            form.dataset.confirmed = 'true';
            form.submit();
        }
    });
});

document.addEventListener('click', function (event) {
    const target = event.target.closest('button[data-confirm], a[data-confirm]');
    if (!target || target.tagName === 'FORM') {
        return;
    }

    if (target.tagName === 'A' && target.href) {
        event.preventDefault();
        window.showConfirm({
            title: target.dataset.confirmTitle || 'Konfirmasi',
            html: target.dataset.confirm,
            icon: target.dataset.confirmIcon || 'warning',
            confirmText: target.dataset.confirmBtn || '<i class="bi bi-check me-1"></i> Ya, Lanjutkan',
            confirmClass: target.dataset.confirmClass || 'btn btn-primary'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = target.href;
            }
        });
        return;
    }

    const parentForm = target.form;
    if (target.type === 'submit' && parentForm && parentForm.dataset.confirmed !== 'true') {
        event.preventDefault();
        window.showConfirm({
            title: target.dataset.confirmTitle || 'Konfirmasi',
            html: target.dataset.confirm,
            icon: target.dataset.confirmIcon || 'warning',
            confirmText: target.dataset.confirmBtn || '<i class="bi bi-trash me-1"></i> Ya, Lanjutkan',
            confirmClass: target.dataset.confirmClass || 'btn btn-danger'
        }).then((result) => {
            if (result.isConfirmed) {
                parentForm.dataset.confirmed = 'true';
                if (target.name) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = target.name;
                    hiddenInput.value = target.value;
                    parentForm.appendChild(hiddenInput);
                }
                parentForm.submit();
            }
        });
    }
});
