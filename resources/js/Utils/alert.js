import Swal from 'sweetalert2';

/**
 * Konfigurasi SweetAlert2 tersinkronisasi dengan tema Bootstrap 5 & UI ProjectSphere.
 */
const CustomSwal = Swal.mixin({
    customClass: {
        popup: 'rounded-4 shadow-lg border-0 p-4',
        title: 'fs-18 fw-bold text-body mt-2 mb-1',
        htmlContainer: 'fs-13 text-muted mb-3',
        confirmButton: 'btn btn-danger rounded-pill px-4 fw-semibold mx-1',
        cancelButton: 'btn btn-light border rounded-pill px-4 fw-semibold mx-1',
        denyButton: 'btn btn-secondary rounded-pill px-4 fw-semibold mx-1',
    },
    buttonsStyling: false,
});

/**
 * Alert Konfirmasi Penghapusan Modern.
 */
export const confirmDelete = async ({
    title = 'Apakah Anda yakin?',
    text = 'Data yang dihapus tidak dapat dipulihkan kembali.',
    confirmButtonText = 'Ya, Hapus!',
    cancelButtonText = 'Batal',
} = {}) => {
    const result = await CustomSwal.fire({
        title,
        text,
        icon: 'warning',
        iconColor: '#dc3545',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        focusCancel: true,
    });

    return result.isConfirmed;
};

/**
 * Alert Konfirmasi Tindakan Umum (Ubah Role, Status, dll).
 */
export const confirmAction = async ({
    title = 'Konfirmasi Tindakan',
    text = 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
    confirmButtonText = 'Ya, Lanjutkan',
    cancelButtonText = 'Batal',
    icon = 'question',
    confirmButtonClass = 'btn btn-primary rounded-pill px-4 fw-semibold mx-1',
} = {}) => {
    const result = await CustomSwal.fire({
        title,
        text,
        icon,
        iconColor: '#0284c7',
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'rounded-4 shadow-lg border-0 p-4',
            title: 'fs-18 fw-bold text-body mt-2 mb-1',
            htmlContainer: 'fs-13 text-muted mb-3',
            confirmButton: confirmButtonClass,
            cancelButton: 'btn btn-light border rounded-pill px-4 fw-semibold mx-1',
        },
    });

    return result.isConfirmed;
};

/**
 * Notifikasi Peringatan (Warning Alert).
 */
export const showWarning = (title = 'Perhatian', text = '') => {
    return CustomSwal.fire({
        icon: 'warning',
        iconColor: '#f59e0b',
        title,
        text,
        confirmButtonText: 'Saya Mengerti',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0 p-4',
            title: 'fs-18 fw-bold text-body mt-2 mb-1',
            htmlContainer: 'fs-13 text-muted mb-3',
            confirmButton: 'btn btn-primary rounded-pill px-4 fw-semibold',
        },
    });
};

/**
 * Notifikasi Sukses.
 */
export const showSuccess = (title = 'Berhasil', text = '') => {
    return CustomSwal.fire({
        icon: 'success',
        iconColor: '#22c55e',
        title,
        text,
        confirmButtonText: 'Selesai',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0 p-4',
            title: 'fs-18 fw-bold text-body mt-2 mb-1',
            htmlContainer: 'fs-13 text-muted mb-3',
            confirmButton: 'btn btn-primary rounded-pill px-4 fw-semibold',
        },
    });
};

/**
 * Notifikasi Error / Gagal.
 */
export const showError = (title = 'Terjadi Kesalahan', text = '') => {
    return CustomSwal.fire({
        icon: 'error',
        iconColor: '#dc3545',
        title,
        text,
        confirmButtonText: 'Tutup',
        customClass: {
            popup: 'rounded-4 shadow-lg border-0 p-4',
            title: 'fs-18 fw-bold text-body mt-2 mb-1',
            htmlContainer: 'fs-13 text-muted mb-3',
            confirmButton: 'btn btn-danger rounded-pill px-4 fw-semibold',
        },
    });
};

export default CustomSwal;
