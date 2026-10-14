/**
 * Date and Time utilities for Project Management
 * Localized in Indonesian (id-ID) and handles YYYY-MM-DD
 * date strings accurately without UTC shifting.
 */

export function formatDate(dateStr) {
    if (!dateStr) return '-';
    try {
        const clean = typeof dateStr === 'string' ? dateStr.substring(0, 10) : dateStr;
        if (typeof clean === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(clean)) {
            const [y, m, d] = clean.split('-').map(Number);
            const date = new Date(y, m - 1, d);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
}

export function formatDateShort(dateStr) {
    if (!dateStr) return '';
    try {
        const clean = typeof dateStr === 'string' ? dateStr.substring(0, 10) : dateStr;
        if (typeof clean === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(clean)) {
            const [y, m, d] = clean.split('-').map(Number);
            const date = new Date(y, m - 1, d);
            return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
        }
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    } catch {
        return dateStr;
    }
}

export function formatDueDate(dateStr) {
    if (!dateStr) return '';
    try {
        const clean = typeof dateStr === 'string' ? dateStr.substring(0, 10) : dateStr;
        if (typeof clean === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(clean)) {
            const [y, m, d] = clean.split('-').map(Number);
            const target = new Date(y, m - 1, d);
            target.setHours(0, 0, 0, 0);

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const diffDays = Math.round((target - today) / (1000 * 60 * 60 * 24));
            if (diffDays === 0) return 'Hari ini';
            if (diffDays === 1) return 'Besok';
            if (diffDays === -1) return 'Kemarin';

            if (target.getFullYear() === today.getFullYear()) {
                return target.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
            }
            return target.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
        }
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    } catch {
        return dateStr;
    }
}

export function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;

        const now = new Date();
        const diffSeconds = Math.floor((now - d) / 1000);

        if (diffSeconds >= 0 && diffSeconds < 60) return 'Baru saja';
        if (diffSeconds >= 60 && diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)} mnt lalu`;
        if (diffSeconds >= 3600 && diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)} jam lalu`;

        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
}

export function isOverdueDate(dateStr, isDone = false) {
    if (!dateStr || isDone) return false;
    try {
        const clean = typeof dateStr === 'string' ? dateStr.substring(0, 10) : dateStr;
        if (typeof clean === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(clean)) {
            const [y, m, d] = clean.split('-').map(Number);
            const target = new Date(y, m - 1, d);
            target.setHours(0, 0, 0, 0);

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            return target < today;
        }
        const d = new Date(dateStr);
        return !isNaN(d.getTime()) && d < new Date();
    } catch {
        return false;
    }
}

