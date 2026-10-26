<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { formatDateShort } from '@/Utils/date';

const props = defineProps({
    tasks: {
        type: Array,
        required: true,
    },
    stages: {
        type: Array,
        default: () => [],
    },
    readOnly: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['open-task', 'update-task-dates']);

// Scale view: 'weeks' (default as in image), 'days', 'months'
const scale = ref('weeks');
const searchQuery = ref('');
const selectedStageFilter = ref('all');

// Navigation offset in weeks
const currentOffset = ref(0);

// Ref to canvas element for measuring width during drag/resize
const canvasRef = ref(null);

// Drag & Resize State
const dragAction = ref(null);
const wasJustDragging = ref(false);

// Colors matching task priority / status
const getTaskColor = (task) => {
    if (task.stage?.color) return task.stage.color;
    switch (task.priority) {
        case 'urgent': return '#ef4444';
        case 'high': return '#f97316';
        case 'medium': return '#10b981'; // Green like image
        default: return '#10b981';
    }
};

// Filtered tasks
const filteredTasks = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return props.tasks.filter((t) => {
        let matchesSearch = true;
        if (q) {
            const titleMatch = t.title?.toLowerCase().includes(q);
            const descMatch = t.description?.toLowerCase().includes(q);
            const priorityMatch = t.priority?.toLowerCase().includes(q);
            const stageMatch = (t.stage?.name || '')?.toLowerCase().includes(q);
            const idMatch = String(t.id).includes(q) || `#${t.id}`.includes(q);
            const assigneeMatch = t.assignees?.some(
                (a) => a.name?.toLowerCase().includes(q) || a.email?.toLowerCase().includes(q)
            );
            matchesSearch = !!(titleMatch || descMatch || priorityMatch || stageMatch || idMatch || assigneeMatch);
        }

        const matchesStage = selectedStageFilter.value === 'all' || t.stage_id == selectedStageFilter.value;
        return matchesSearch && matchesStage;
    });
});

// Helper Date Functions
const getMonday = (d) => {
    const date = new Date(d);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    return new Date(date.setDate(diff));
};

const getWeekNumber = (d) => {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    date.setUTCDate(date.getUTCDate() + 4 - (date.getUTCDay() || 7));
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
};

// Base reference date based on today
const baseDate = computed(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d;
});

// Watch scale change to reset navigation offset
watch(scale, () => {
    currentOffset.value = 0;
});

// Dynamic timeline columns based on active scale ('days', 'weeks', 'months')
const timelineColumns = computed(() => {
    const cols = [];

    if (scale.value === 'days') {
        // 14 daily columns centered around today + offset (each offset = 7 days)
        const start = new Date(baseDate.value);
        start.setDate(start.getDate() + (currentOffset.value * 7) - 2);

        for (let i = 0; i < 14; i++) {
            const colStart = new Date(start);
            colStart.setDate(colStart.getDate() + i);
            colStart.setHours(0, 0, 0, 0);

            const colEnd = new Date(colStart);
            colEnd.setHours(23, 59, 59, 999);

            const dayName = colStart.toLocaleDateString('id-ID', { weekday: 'short' }).replace('.', '');
            const dayNum = colStart.getDate();

            const isToday = colStart.toDateString() === new Date().toDateString();
            const isWeekend = colStart.getDay() === 0 || colStart.getDay() === 6;

            cols.push({
                index: i,
                startDate: colStart,
                endDate: colEnd,
                label: `${dayName} ${dayNum}`,
                isCurrent: isToday,
                isWeekend,
            });
        }
    } else if (scale.value === 'months') {
        // 8 monthly columns (each offset = 2 months)
        const year = baseDate.value.getFullYear();
        const baseMonth = baseDate.value.getMonth() + (currentOffset.value * 2);

        for (let i = -1; i < 7; i++) {
            const colStart = new Date(year, baseMonth + i, 1, 0, 0, 0, 0);
            const colEnd = new Date(year, baseMonth + i + 1, 0, 23, 59, 59, 999);

            const monthName = colStart.toLocaleDateString('id-ID', { month: 'short' });
            const colYear = colStart.getFullYear();

            const now = new Date();
            const isCurrentMonth = colStart.getMonth() === now.getMonth() && colStart.getFullYear() === now.getFullYear();

            cols.push({
                index: i,
                startDate: colStart,
                endDate: colEnd,
                label: `${monthName} ${colYear}`,
                isCurrent: isCurrentMonth,
                isWeekend: false,
            });
        }
    } else {
        // Default: 'weeks' (7 weekly columns, each offset = 2 weeks)
        const monday = getMonday(baseDate.value);
        monday.setDate(monday.getDate() + (currentOffset.value * 14));
        monday.setHours(0, 0, 0, 0);

        for (let i = -1; i < 6; i++) {
            const weekStart = new Date(monday);
            weekStart.setDate(weekStart.getDate() + i * 7);
            weekStart.setHours(0, 0, 0, 0);

            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekEnd.getDate() + 6);
            weekEnd.setHours(23, 59, 59, 999);

            const wNum = getWeekNumber(weekStart);
            const startMonth = weekStart.toLocaleDateString('id-ID', { month: 'short' });
            const endMonth = weekEnd.toLocaleDateString('id-ID', { month: 'short' });
            const startDay = weekStart.getDate();
            const endDay = weekEnd.getDate();

            const isCurrentWeek = wNum === getWeekNumber(new Date()) && weekStart.getFullYear() === new Date().getFullYear();

            const dateRangeLabel = startMonth === endMonth
                ? `${startDay} - ${endDay} ${startMonth}`
                : `${startDay} ${startMonth} - ${endDay} ${endMonth}`;

            cols.push({
                index: i,
                startDate: weekStart,
                endDate: weekEnd,
                label: `W${wNum} (${dateRangeLabel})`,
                isCurrent: isCurrentWeek,
                isWeekend: false,
            });
        }
    }

    return cols;
});

// Dynamic Header Groups for Month / Year Top Bar (aligns 1:1 with columns)
const headerGroups = computed(() => {
    const groups = [];
    if (!timelineColumns.value.length) return groups;

    for (const col of timelineColumns.value) {
        let title;
        if (scale.value === 'months') {
            title = `Tahun ${col.startDate.getFullYear()}`;
        } else {
            title = col.startDate.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
        }

        const lastGroup = groups[groups.length - 1];
        if (!lastGroup || lastGroup.title !== title) {
            groups.push({
                title,
                count: 1,
            });
        } else {
            lastGroup.count++;
        }
    }

    return groups;
});

// Overall timeline start and end dates in ms
const timelineStartMs = computed(() => timelineColumns.value[0]?.startDate.getTime() || 0);
const timelineEndMs = computed(() => timelineColumns.value[timelineColumns.value.length - 1]?.endDate.getTime() || 1);
const totalTimelineDurationMs = computed(() => Math.max(1, timelineEndMs.value - timelineStartMs.value));

// Today marker calculation (%)
const todayPercent = computed(() => {
    const now = new Date().getTime();
    if (now < timelineStartMs.value || now > timelineEndMs.value) return -10;
    return ((now - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
});

// Format Task Start & Due Date for left table
const formatTaskDates = (task) => {
    if (!task.start_date && !task.due_at) return 'Tanpa tanggal';

    if (task.start_date && task.due_at) {
        if (task.start_date === task.due_at) return formatDateShort(task.due_at);
        return `${formatDateShort(task.start_date)} - ${formatDateShort(task.due_at)}`;
    }

    if (task.due_at) return formatDateShort(task.due_at);
    return formatDateShort(task.start_date);
};

// Date range calculation helper for a task
const getTaskDateRange = (task) => {
    let startMs = null;
    let endMs = null;

    if (task.start_date) {
        const clean = typeof task.start_date === 'string' ? task.start_date.substring(0, 10) : '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(clean)) {
            const [y, m, d] = clean.split('-').map(Number);
            startMs = new Date(y, m - 1, d, 0, 0, 0, 0).getTime();
        }
    }

    if (task.due_at) {
        const clean = typeof task.due_at === 'string' ? task.due_at.substring(0, 10) : '';
        if (/^\d{4}-\d{2}-\d{2}$/.test(clean)) {
            const [y, m, d] = clean.split('-').map(Number);
            endMs = new Date(y, m - 1, d, 23, 59, 59, 999).getTime();
        }
    }

    if (!startMs && !endMs) {
        const created = task.created_at ? new Date(task.created_at) : new Date();
        created.setHours(0, 0, 0, 0);
        startMs = created.getTime();
        endMs = startMs + 3 * 86400000 - 1;
    } else if (!startMs) {
        startMs = endMs - 2 * 86400000 + 1;
    } else if (!endMs) {
        endMs = startMs + 2 * 86400000 - 1;
    }

    return { startMs, endMs };
};

const formatDateToYmd = (ms) => {
    const d = new Date(ms);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

// Calculate date range for a task, taking active drag & dependency cascade into account
const getTaskDateRangeWithCascade = (task) => {
    // If this task itself is being dragged
    if (dragAction.value && dragAction.value.task.id === task.id) {
        return {
            startMs: dragAction.value.currentStartMs,
            endMs: dragAction.value.currentEndMs,
            isDragging: true,
            isCascading: false,
        };
    }

    const range = getTaskDateRange(task);

    // If another task is being dragged, check if this task depends on the dragged task
    if (dragAction.value && task.dependencies?.some((d) => d.id === dragAction.value.task.id)) {
        const draggedEndMs = dragAction.value.currentEndMs;
        // If the dragged task finishes on or after this task's start date
        if (range.startMs <= draggedEndMs) {
            const duration = Math.max(86400000 - 1, range.endMs - range.startMs);
            const shiftedStartMs = draggedEndMs + 86400000;
            const shiftedEndMs = shiftedStartMs + duration;
            return {
                startMs: shiftedStartMs,
                endMs: shiftedEndMs,
                isDragging: false,
                isCascading: true,
            };
        }
    }

    return {
        ...range,
        isDragging: false,
        isCascading: false,
    };
};

// Calculate Bar position and width in %
const getTaskBarStyle = (task) => {
    const range = getTaskDateRangeWithCascade(task);
    const startMs = range.startMs;
    const endMs = range.endMs;

    const leftPercent = ((startMs - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
    const rightPercent = ((endMs - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
    const widthPercent = Math.max(1.5, rightPercent - leftPercent);

    return {
        left: `${leftPercent}%`,
        width: `${widthPercent}%`,
        backgroundColor: getTaskColor(task),
        opacity: range.isCascading ? 0.85 : 1,
        outline: range.isCascading ? '2px dashed #f59e0b' : 'none',
        outlineOffset: '2px',
    };
};

const getTaskBarMeta = (task) => {
    const range = getTaskDateRangeWithCascade(task);
    const leftPercent = ((range.startMs - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
    const rightPercent = ((range.endMs - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;

    return {
        isOverflowLeft: leftPercent < 0,
        isOverflowRight: rightPercent > 100,
        isCascading: !!range.isCascading,
    };
};

const getDependencyTooltip = (task) => {
    if (!task.dependencies?.length) return '';
    const titles = task.dependencies.map((d) => d.title).join(', ');
    return `Menunggu penyelesaian: ${titles}`;
};

// Live tooltip dates during drag
const activeDragDatesText = computed(() => {
    if (!dragAction.value) return '';
    const startStr = formatDateToYmd(dragAction.value.currentStartMs);
    const endStr = formatDateToYmd(dragAction.value.currentEndMs);
    return `${formatDateShort(startStr)} - ${formatDateShort(endStr)}`;
});

// Drag and Resize Handlers
const onBarMouseDown = (e, task) => {
    if (props.readOnly) return;
    if (e.button !== 0) return;
    if (e.target.closest('.gantt-resize-handle')) return;

    startDrag(e, task, 'move');
};

const onHandleMouseDown = (e, task, mode) => {
    if (props.readOnly) return;
    if (e.button !== 0) return;
    e.stopPropagation();
    startDrag(e, task, mode);
};

const startDrag = (e, task, mode) => {
    const canvas = canvasRef.value;
    if (!canvas) return;

    const canvasWidth = canvas.getBoundingClientRect().width || 1;
    const { startMs, endMs } = getTaskDateRange(task);

    dragAction.value = {
        task,
        mode,
        startX: e.clientX,
        originalStartMs: startMs,
        originalEndMs: endMs,
        currentStartMs: startMs,
        currentEndMs: endMs,
        canvasWidth,
        hasMoved: false,
    };

    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
};

const onMouseMove = (e) => {
    if (!dragAction.value) return;

    const action = dragAction.value;
    const deltaX = e.clientX - action.startX;

    if (Math.abs(deltaX) > 3) {
        action.hasMoved = true;
    }

    const deltaMs = (deltaX / action.canvasWidth) * totalTimelineDurationMs.value;
    const deltaDays = Math.round(deltaMs / 86400000);
    const snappedDeltaMs = deltaDays * 86400000;

    if (action.mode === 'move') {
        action.currentStartMs = action.originalStartMs + snappedDeltaMs;
        action.currentEndMs = action.originalEndMs + snappedDeltaMs;
    } else if (action.mode === 'resize-start') {
        const newStart = action.originalStartMs + snappedDeltaMs;
        // Don't let start exceed end (leave at least 1 day duration)
        action.currentStartMs = Math.min(newStart, action.currentEndMs - 86400000 + 1);
    } else if (action.mode === 'resize-end') {
        const newEnd = action.originalEndMs + snappedDeltaMs;
        // Don't let end be less than start (leave at least 1 day duration)
        action.currentEndMs = Math.max(newEnd, action.currentStartMs + 86400000 - 1);
    }
};

const onMouseUp = () => {
    if (!dragAction.value) return;

    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);

    const action = dragAction.value;
    dragAction.value = null;

    if (action.hasMoved) {
        wasJustDragging.value = true;
        setTimeout(() => {
            wasJustDragging.value = false;
        }, 150);

        const newStartStr = formatDateToYmd(action.currentStartMs);
        const newEndStr = formatDateToYmd(action.currentEndMs);

        // Check if dates actually changed
        if (newStartStr !== action.task.start_date || newEndStr !== action.task.due_at) {
            emit('update-task-dates', {
                taskId: action.task.id,
                startDate: newStartStr,
                dueAt: newEndStr,
            });
        }
    }
};

const onBarClick = (task) => {
    if (wasJustDragging.value) return;
    emit('open-task', task);
};

onUnmounted(() => {
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
});

const jumpToToday = () => {
    currentOffset.value = 0;
};

const prevTime = () => {
    currentOffset.value -= 1;
};

const nextTime = () => {
    currentOffset.value += 1;
};
</script>

<template>
    <div class="gantt-container rounded-3 border border-secondary-subtle overflow-hidden shadow-sm">
        <!-- Top Toolbar Header -->
        <div class="gantt-header d-flex flex-wrap align-items-center justify-content-between p-2 p-sm-3 border-bottom gap-2">
            <!-- Left Controls: Title & Filters -->
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-grid-fill text-primary fs-18"></i>
                    <h5 class="fw-bold mb-0 text-body">Gantt Timeline</h5>
                </div>

                <div class="vr mx-1 opacity-25 d-none d-sm-block"></div>

                <!-- Stage Filter -->
                <select v-model="selectedStageFilter" class="form-select form-select-sm gantt-select rounded-pill px-3 fs-12">
                    <option value="all">Semua Status</option>
                    <option v-for="stg in stages" :key="stg.id" :value="stg.id">{{ stg.name }}</option>
                </select>

                <!-- Search Input with Clear Button -->
                <div class="position-relative" style="min-width: 170px; max-width: 250px;">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari task, assignee, status..."
                        class="form-control form-control-sm rounded-pill ps-4 pe-4 fs-12 gantt-input"
                    />
                    <i class="ri-search-line position-absolute top-50 start-0 translate-middle-y ms-2 text-muted fs-12"></i>
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="btn btn-link btn-sm p-0 position-absolute top-50 end-0 translate-middle-y me-2 text-muted text-decoration-none border-0"
                        @click="searchQuery = ''"
                        title="Hapus pencarian"
                    >
                        <i class="ri-close-circle-fill fs-14"></i>
                    </button>
                </div>
            </div>

            <!-- Right Controls: Auto Fit, Scale Dropdown, Navigation -->
            <div class="d-flex flex-wrap align-items-center gap-2 ms-auto ms-sm-0">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill px-3 fs-12 fw-semibold d-flex align-items-center gap-1"
                    @click="jumpToToday"
                >
                    <i class="ri-calendar-check-line text-primary"></i> Hari Ini
                </button>

                <!-- Scale Mode -->
                <select v-model="scale" class="form-select form-select-sm gantt-select rounded-pill px-3 fs-12 fw-semibold" style="width: 105px;">
                    <option value="weeks">Weeks</option>
                    <option value="days">Days</option>
                    <option value="months">Months</option>
                </select>

                <!-- Prev / Next Navigation -->
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary fs-12 px-2" @click="prevTime" title="Mundur">
                        <i class="ri-subtract-line"></i>
                    </button>
                    <button type="button" class="btn btn-outline-secondary fs-12 px-2" @click="nextTime" title="Maju">
                        <i class="ri-add-line"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Gantt Body: Left Tasks Pane + Right Timeline Grid -->
        <div class="gantt-body d-flex position-relative overflow-x-auto" style="-webkit-overflow-scrolling: touch;">
            <!-- LEFT PANE: Task Titles & Dates -->
            <div class="gantt-left-pane border-end flex-shrink-0" style="width: min(340px, 45vw); min-width: 170px;">
                <!-- Left Header Row 1 (Matches Month Top Bar: height 32px) -->
                <div class="gantt-top-header border-bottom px-3 fs-12 fw-bold text-muted d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-1">
                        <span>Daftar Tugas</span>
                        <span v-if="searchQuery.trim() || selectedStageFilter !== 'all'" class="text-primary fs-10 fw-normal">(difilter)</span>
                    </div>
                    <span class="badge bg-secondary-subtle text-secondary rounded-pill fs-10">
                        {{ filteredTasks.length }}<template v-if="filteredTasks.length !== tasks.length"> / {{ tasks.length }}</template> Task
                    </span>
                </div>
                <!-- Left Header Row 2 (Matches Scale Columns: height 42px) -->
                <div class="gantt-table-header d-flex align-items-center justify-content-between px-3 border-bottom text-muted fs-11 fw-bold text-uppercase">
                    <span>Task Name</span>
                    <span>Timeline</span>
                </div>

                <!-- Left Rows (Task List) -->
                <div class="gantt-task-list">
                    <div
                        v-for="task in filteredTasks"
                        :key="task.id"
                        class="gantt-row gantt-task-item d-flex align-items-center justify-content-between px-3 border-bottom cursor-pointer"
                        @click="emit('open-task', task)"
                    >
                        <div class="d-flex align-items-center gap-2 text-truncate pe-2">
                            <span class="status-indicator" :style="{ backgroundColor: getTaskColor(task) }"></span>
                            <span class="fw-semibold fs-13 text-body text-truncate" :title="task.title">{{ task.title }}</span>
                            <!-- Dependency badge in list -->
                            <span
                                v-if="task.dependencies?.length"
                                class="badge bg-secondary-subtle text-secondary rounded-pill px-1-5 py-0 fs-10 flex-shrink-0"
                                :title="`${task.dependencies.length} dependensi tugas`"
                            >
                                <i class="ri-links-line me-1"></i>{{ task.dependencies.length }}
                            </span>
                        </div>
                        <span class="fs-11 text-muted text-nowrap">
                            {{ formatTaskDates(task) }}
                        </span>
                    </div>

                    <div v-if="filteredTasks.length === 0" class="p-4 text-center text-muted fs-13">
                        <i class="ri-search-line fs-20 d-block mb-1 text-muted opacity-50"></i>
                        <div class="fw-semibold">Tidak ada task yang cocok</div>
                        <div class="fs-11 text-muted mt-1">Coba kata kunci lain atau bersihkan filter</div>
                        <button
                            v-if="searchQuery || selectedStageFilter !== 'all'"
                            type="button"
                            class="btn btn-sm btn-outline-primary rounded-pill mt-2 fs-11 px-3"
                            @click="searchQuery = ''; selectedStageFilter = 'all'"
                        >
                            Reset Filter
                        </button>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANE: Time Columns & Horizontal Bars -->
            <div class="gantt-right-pane flex-grow-1 overflow-x-auto position-relative">
                <!-- Month & Year Top Bar (height 32px) -->
                <div class="gantt-month-header d-flex border-bottom">
                    <div
                        v-for="(grp, idx) in headerGroups"
                        :key="idx"
                        class="text-center px-2 fs-12 fw-bold text-muted border-end text-truncate d-flex align-items-center justify-content-center"
                        :style="{ flex: grp.count }"
                    >
                        <span>{{ grp.title }}</span>
                    </div>
                </div>

                <!-- Scale Columns Header (height 42px) -->
                <div class="gantt-weeks-header d-flex border-bottom">
                    <div
                        v-for="col in timelineColumns"
                        :key="col.index"
                        class="gantt-col-header text-center border-end flex-fill fs-11 fw-semibold text-truncate d-flex align-items-center justify-content-center px-1"
                        :class="{
                            'current-col-header': col.isCurrent,
                            'weekend-header': col.isWeekend,
                        }"
                    >
                        <span :class="col.isCurrent ? 'badge bg-primary text-white px-2 py-1 rounded-pill' : 'text-muted'">
                            {{ col.label }}
                        </span>
                    </div>
                </div>

                <!-- Grid Canvas & Task Bars -->
                <div class="gantt-canvas position-relative" ref="canvasRef">
                    <!-- Vertical Grid Lines -->
                    <div class="gantt-grid-lines d-flex position-absolute top-0 bottom-0 start-0 end-0 pointer-events-none">
                        <div
                            v-for="col in timelineColumns"
                            :key="col.index"
                            class="gantt-grid-col flex-fill border-end"
                            :class="{
                                'current-col-bg': col.isCurrent,
                                'weekend-bg': col.isWeekend,
                            }"
                        ></div>
                    </div>

                    <!-- Vertical Today Line (Blue Line like ClickUp) -->
                    <div
                        v-if="todayPercent >= 0 && todayPercent <= 100"
                        class="today-vertical-line position-absolute top-0 bottom-0 pointer-events-none"
                        :style="{ left: `${todayPercent}%` }"
                    >
                        <div class="today-marker-dot"></div>
                    </div>

                    <!-- Task Horizontal Rows -->
                    <div
                        v-for="task in filteredTasks"
                        :key="task.id"
                        class="gantt-row gantt-bar-row position-relative border-bottom d-flex align-items-center"
                    >
                        <!-- Timeline Bar Pill -->
                        <div
                            class="gantt-bar rounded-pill position-absolute d-flex align-items-center px-2 shadow-xs"
                            :class="{
                                'is-dragging': dragAction?.task.id === task.id,
                                'cursor-grab': !readOnly,
                                'cursor-pointer': readOnly,
                                'is-cascading-preview': getTaskBarMeta(task).isCascading,
                            }"
                            :style="getTaskBarStyle(task)"
                            :title="`${task.title} (${formatTaskDates(task)})`"
                            @mousedown="onBarMouseDown($event, task)"
                            @click="onBarClick(task)"
                        >
                            <!-- Overflow Left Indicator -->
                            <i
                                v-if="getTaskBarMeta(task).isOverflowLeft"
                                class="ri-arrow-left-s-line text-white-50 fs-12 me-1 flex-shrink-0"
                                title="Tugas dimulai sebelum periode yang terlihat"
                            ></i>

                            <!-- Left Resize Handle (start_date) - only if visible on canvas -->
                            <div
                                v-if="!readOnly && !getTaskBarMeta(task).isOverflowLeft"
                                class="gantt-resize-handle start-handle"
                                title="Tarik untuk ubah tanggal mulai"
                                @mousedown.stop="onHandleMouseDown($event, task, 'resize-start')"
                            ></div>

                            <!-- Dependency link indicator badge on Gantt Bar -->
                            <span
                                v-if="task.dependencies?.length"
                                class="badge bg-white text-dark rounded-circle p-0 me-1 d-inline-flex align-items-center justify-content-center shadow-2xs flex-shrink-0 cursor-pointer"
                                style="width: 16px; height: 16px; font-size: 9px;"
                                :title="getDependencyTooltip(task)"
                            >
                                <i class="ri-links-line text-primary"></i>
                            </span>

                            <span class="gantt-bar-title text-white fs-11 fw-semibold text-truncate pe-1">
                                {{ task.title }}
                            </span>

                            <!-- Cascading Live Badge Indicator -->
                            <span
                                v-if="getTaskBarMeta(task).isCascading"
                                class="badge bg-warning text-dark rounded-pill px-1-5 py-0 fs-9 ms-1 flex-shrink-0 fw-bold"
                                title="Otomatis bergeser mengikuti tugas prasyarat"
                            >
                                Auto-Shift
                            </span>

                            <!-- Overflow Right Indicator -->
                            <i
                                v-if="getTaskBarMeta(task).isOverflowRight"
                                class="ri-arrow-right-s-line text-white-50 fs-12 ms-auto flex-shrink-0"
                                title="Tugas berlanjut setelah periode yang terlihat"
                            ></i>

                            <!-- Right Resize Handle (due_at) - only if visible on canvas -->
                            <div
                                v-if="!readOnly && !getTaskBarMeta(task).isOverflowRight"
                                class="gantt-resize-handle end-handle"
                                title="Tarik untuk ubah tenggat waktu"
                                @mousedown.stop="onHandleMouseDown($event, task, 'resize-end')"
                            ></div>

                            <!-- Live Floating Tooltip during Dragging -->
                            <div
                                v-if="dragAction?.task.id === task.id"
                                class="gantt-drag-tooltip position-absolute"
                            >
                                <div class="badge bg-dark text-white shadow py-1 px-2 fs-11 fw-semibold rounded-pill d-flex align-items-center gap-1">
                                    <i class="ri-calendar-line text-warning"></i>
                                    <span>{{ activeDragDatesText }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Info Tip -->
        <div class="gantt-footer p-2 px-3 border-top bg-light-subtle d-flex flex-wrap align-items-center justify-content-between gap-2 fs-11 text-muted">
            <div class="d-flex align-items-center gap-2">
                <i class="ri-lightbulb-line text-warning"></i>
                <span v-if="!readOnly">Geser timeline bar untuk memindahkan tanggal tugas, atau tarik sisi kiri/kanan untuk menyesuaikan durasi.</span>
                <span v-else>Mode Viewer (Hanya Baca): Anda dapat melihat timeline dan membuka rincian untuk berdiskusi.</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="d-flex align-items-center gap-1">
                    <span class="badge rounded-circle bg-primary p-1"></span> Garis Biru = Hari Ini
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge rounded-circle bg-success p-1"></span> Bar Hijau = Durasi Task
                </span>
                <span class="d-flex align-items-center gap-1">
                    <i class="ri-links-line text-primary"></i> = Ada Dependensi
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gantt-container {
    background-color: var(--bs-card-bg, var(--bs-body-bg));
    color: var(--bs-body-color);
}

.gantt-header {
    background-color: var(--bs-tertiary-bg);
}

.gantt-left-pane {
    width: 340px;
    background-color: var(--bs-card-bg, var(--bs-body-bg));
    z-index: 2;
}

.gantt-top-header,
.gantt-month-header {
    height: 32px;
    min-height: 32px;
    max-height: 32px;
    background-color: var(--bs-tertiary-bg);
    box-sizing: border-box;
}

.gantt-table-header,
.gantt-weeks-header {
    height: 42px;
    min-height: 42px;
    max-height: 42px;
    background-color: var(--bs-tertiary-bg);
    box-sizing: border-box;
    overflow: hidden;
}

.gantt-col-header {
    height: 42px;
    min-height: 42px;
    max-height: 42px;
    box-sizing: border-box;
}

.gantt-row {
    height: 44px;
    min-height: 44px;
    max-height: 44px;
    box-sizing: border-box;
}

.gantt-task-item {
    height: 44px;
    min-height: 44px;
    max-height: 44px;
    box-sizing: border-box;
    transition: background-color 0.15s ease;
}

.gantt-task-item:hover {
    background-color: var(--bs-tertiary-bg);
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.gantt-right-pane {
    min-width: 650px;
}

.gantt-canvas {
    overflow: hidden;
    min-height: 120px;
}

.current-col-header {
    background-color: rgba(var(--bs-primary-rgb, 14, 165, 233), 0.12);
}

.current-col-bg {
    background-color: rgba(var(--bs-primary-rgb, 14, 165, 233), 0.05);
}

.weekend-header {
    background-color: rgba(0, 0, 0, 0.03);
}

.weekend-bg {
    background-color: rgba(0, 0, 0, 0.015);
}

.today-vertical-line {
    width: 2px;
    background-color: var(--bs-primary, #0284c7);
    z-index: 10;
}

.today-marker-dot {
    width: 8px;
    height: 8px;
    background-color: var(--bs-primary, #0284c7);
    border-radius: 50%;
    position: absolute;
    top: -4px;
    left: -3px;
    box-shadow: 0 0 6px var(--bs-primary, #0284c7);
}

.gantt-bar-row {
    height: 44px;
    min-height: 44px;
    max-height: 44px;
    box-sizing: border-box;
    background-color: transparent;
}

.gantt-bar-row:hover {
    background-color: rgba(var(--bs-body-color-rgb, 0, 0, 0), 0.03);
}

.gantt-bar {
    height: 26px;
    top: 9px;
    z-index: 5;
    transition: transform 0.15s ease, filter 0.15s ease, box-shadow 0.15s ease;
    user-select: none;
}

.gantt-bar.is-cascading-preview {
    animation: pulse-border 1.5s infinite;
}

@keyframes pulse-border {
    0%, 100% { outline-color: #f59e0b; }
    50% { outline-color: #ef4444; }
}

.gantt-bar:hover {
    transform: translateY(-1px);
    filter: brightness(1.1);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
}

.gantt-bar.is-dragging {
    transition: none !important;
    z-index: 25 !important;
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.35) !important;
    filter: brightness(1.12);
}

.cursor-grab {
    cursor: grab;
}

.cursor-grab:active, .is-dragging {
    cursor: grabbing !important;
}

.gantt-resize-handle {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 10px;
    cursor: ew-resize;
    z-index: 12;
    border-radius: 999px;
    transition: background-color 0.15s;
}

.gantt-resize-handle:hover {
    background-color: rgba(255, 255, 255, 0.45);
}

.start-handle {
    left: 0;
}

.end-handle {
    right: 0;
}

.gantt-drag-tooltip {
    top: -32px;
    left: 50%;
    transform: translateX(-50%);
    pointer-events: none;
    z-index: 99;
}

.pointer-events-none {
    pointer-events: none;
}

.cursor-pointer {
    cursor: pointer;
}

/* Custom dark/light styling inputs */
.gantt-input, .gantt-select {
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: var(--bs-border-color);
}

.gantt-input:focus, .gantt-select:focus {
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    border-color: var(--bs-primary);
}
</style>
