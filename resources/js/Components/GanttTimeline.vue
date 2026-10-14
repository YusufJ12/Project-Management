<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    tasks: {
        type: Array,
        required: true,
    },
    stages: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['open-task', 'update-task-dates']);

// Scale view: 'weeks' (default as in image), 'days', 'months'
const scale = ref('weeks');
const searchQuery = ref('');
const selectedStageFilter = ref('all');

// Navigation offset in weeks
const currentOffset = ref(0);

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
    return props.tasks.filter((t) => {
        const matchesSearch = !searchQuery.value.trim() ||
            t.title.toLowerCase().includes(searchQuery.value.toLowerCase());
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

// Compute timeline boundaries (generate 6 weeks columns around current view)
const baseMonday = computed(() => {
    const now = new Date();
    const monday = getMonday(now);
    monday.setDate(monday.getDate() + currentOffset.value * 7);
    monday.setHours(0, 0, 0, 0);
    return monday;
});

const timelineWeeks = computed(() => {
    const weeks = [];
    const start = new Date(baseMonday.value);

    for (let i = -1; i < 6; i++) {
        const weekStart = new Date(start);
        weekStart.setDate(weekStart.getDate() + i * 7);

        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 4); // Mon - Fri work week display

        const wNum = getWeekNumber(weekStart);
        const startMonth = weekStart.toLocaleDateString('en-US', { month: 'short' });
        const startDay = weekStart.getDate();
        const endDay = weekEnd.getDate();

        weeks.push({
            index: i,
            weekNumber: wNum,
            startDate: new Date(weekStart),
            endDate: new Date(weekStart.getFullYear(), weekStart.getMonth(), weekStart.getDate() + 6, 23, 59, 59),
            label: `W${wNum} ${startMonth} ${startDay} - ${endDay}`,
            monthYear: weekStart.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
            isCurrentWeek: wNum === getWeekNumber(new Date()) && weekStart.getFullYear() === new Date().getFullYear(),
        });
    }
    return weeks;
});

// Overall timeline start and end dates in ms
const timelineStartMs = computed(() => timelineWeeks.value[0]?.startDate.getTime() || 0);
const timelineEndMs = computed(() => timelineWeeks.value[timelineWeeks.value.length - 1]?.endDate.getTime() || 1);
const totalTimelineDurationMs = computed(() => Math.max(1, timelineEndMs.value - timelineStartMs.value));

// Today marker calculation (%)
const todayPercent = computed(() => {
    const now = new Date().getTime();
    if (now < timelineStartMs.value) return -10;
    if (now > timelineEndMs.value) return 110;
    return ((now - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
});

// Format Task Start & Due Date for left table
const formatTaskDates = (task) => {
    if (!task.start_date && !task.due_at) return 'No dates';
    
    const formatDateShort = (dStr) => {
        if (!dStr) return '';
        const d = new Date(dStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    if (task.start_date && task.due_at) {
        if (task.start_date === task.due_at) return formatDateShort(task.due_at);
        const start = new Date(task.start_date);
        const due = new Date(task.due_at);
        if (start.getMonth() === due.getMonth()) {
            return `${start.toLocaleDateString('en-US', { month: 'short' })} ${start.getDate()} - ${due.getDate()}`;
        }
        return `${formatDateShort(task.start_date)} - ${formatDateShort(task.due_at)}`;
    }

    if (task.due_at) return formatDateShort(task.due_at);
    return formatDateShort(task.start_date);
};

// Calculate Bar position and width in %
const getTaskBarStyle = (task) => {
    // Default fallback dates if not set
    let startMs = task.start_date ? new Date(task.start_date).getTime() : null;
    let endMs = task.due_at ? new Date(task.due_at).getTime() + (24 * 60 * 60 * 1000 - 1) : null;

    if (!startMs && !endMs) {
        // Fallback: use created_at to created_at + 3 days
        const created = task.created_at ? new Date(task.created_at) : new Date();
        startMs = created.getTime();
        endMs = startMs + 3 * 24 * 60 * 60 * 1000;
    } else if (!startMs) {
        startMs = endMs - 2 * 24 * 60 * 60 * 1000;
    } else if (!endMs) {
        endMs = startMs + 2 * 24 * 60 * 60 * 1000;
    }

    // Clip if out of view
    const leftPercent = ((startMs - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
    const rightPercent = ((endMs - timelineStartMs.value) / totalTimelineDurationMs.value) * 100;
    const widthPercent = Math.max(1.5, rightPercent - leftPercent);

    return {
        left: `${Math.max(0, leftPercent)}%`,
        width: `${Math.min(100, widthPercent)}%`,
        backgroundColor: getTaskColor(task),
    };
};

const jumpToToday = () => {
    currentOffset.value = 0;
};

const prevTime = () => {
    currentOffset.value -= 2;
};

const nextTime = () => {
    currentOffset.value += 2;
};
</script>

<template>
    <div class="gantt-container rounded-3 border border-secondary-subtle overflow-hidden shadow-sm">
        <!-- Top Toolbar Header -->
        <div class="gantt-header d-flex flex-wrap align-items-center justify-content-between p-3 border-bottom">
            <!-- Left Controls: Title & Filters -->
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="ri-grid-fill text-primary fs-18"></i>
                    <h5 class="fw-bold mb-0 text-body">Gantt Timeline</h5>
                </div>

                <div class="vr mx-1 opacity-25"></div>

                <!-- Stage Filter -->
                <select v-model="selectedStageFilter" class="form-select form-select-sm gantt-select rounded-pill px-3 fs-12">
                    <option value="all">Semua Status</option>
                    <option v-for="stg in stages" :key="stg.id" :value="stg.id">{{ stg.name }}</option>
                </select>

                <!-- Search Input -->
                <div class="position-relative" style="width: 180px;">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari task..."
                        class="form-control form-control-sm rounded-pill ps-4 fs-12 gantt-input"
                    />
                    <i class="ri-search-line position-absolute top-50 start-0 translate-middle-y ms-2 text-muted fs-12"></i>
                </div>
            </div>

            <!-- Right Controls: Auto Fit, Scale Dropdown, Navigation -->
            <div class="d-flex align-items-center gap-2">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-secondary rounded-pill px-3 fs-12 fw-semibold d-flex align-items-center gap-1"
                    @click="jumpToToday"
                >
                    <i class="ri-calendar-check-line text-primary"></i> Hari Ini
                </button>

                <!-- Scale Mode -->
                <select v-model="scale" class="form-select form-select-sm gantt-select rounded-pill px-3 fs-12 fw-semibold" style="width: 110px;">
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
        <div class="gantt-body d-flex position-relative">
            <!-- LEFT PANE: Task Titles & Dates -->
            <div class="gantt-left-pane border-end flex-shrink-0">
                <!-- Left Header -->
                <div class="gantt-row gantt-table-header d-flex align-items-center justify-content-between px-3 border-bottom text-muted fs-12 fw-bold text-uppercase">
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
                        </div>
                        <span class="fs-11 text-muted text-nowrap">
                            {{ formatTaskDates(task) }}
                        </span>
                    </div>

                    <div v-if="filteredTasks.length === 0" class="p-4 text-center text-muted fs-13">
                        Tidak ada task yang ditemukan.
                    </div>
                </div>
            </div>

            <!-- RIGHT PANE: Time Columns & Horizontal Bars -->
            <div class="gantt-right-pane flex-grow-1 overflow-x-auto position-relative">
                <!-- Month & Year Top Bar -->
                <div class="gantt-month-header border-bottom px-3 py-1 text-center fs-12 fw-bold text-muted">
                    {{ timelineWeeks[1]?.monthYear || 'Timeline' }}
                </div>

                <!-- Weeks / Scale Columns Header -->
                <div class="gantt-weeks-header d-flex border-bottom">
                    <div
                        v-for="w in timelineWeeks"
                        :key="w.index"
                        class="gantt-col-header text-center py-2 px-1 border-end flex-fill fs-11 fw-semibold text-truncate"
                        :class="{ 'current-week-header': w.isCurrentWeek }"
                    >
                        <span :class="w.isCurrentWeek ? 'badge bg-primary text-white px-2 py-1 rounded-pill' : 'text-muted'">
                            {{ w.label }}
                        </span>
                    </div>
                </div>

                <!-- Grid Canvas & Task Bars -->
                <div class="gantt-canvas position-relative">
                    <!-- Vertical Grid Lines -->
                    <div class="gantt-grid-lines d-flex position-absolute top-0 bottom-0 start-0 end-0 pointer-events-none">
                        <div
                            v-for="w in timelineWeeks"
                            :key="w.index"
                            class="gantt-grid-col flex-fill border-end"
                            :class="{ 'current-week-bg': w.isCurrentWeek }"
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
                            class="gantt-bar rounded-pill position-absolute d-flex align-items-center px-2 shadow-xs cursor-pointer"
                            :style="getTaskBarStyle(task)"
                            :title="`${task.title} (${formatTaskDates(task)})`"
                            @click="emit('open-task', task)"
                        >
                            <span class="gantt-bar-title text-white fs-11 fw-semibold text-truncate pe-1">
                                {{ task.title }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Info Tip -->
        <div class="gantt-footer p-2 px-3 border-top bg-light-subtle d-flex align-items-center justify-content-between fs-11 text-muted">
            <div class="d-flex align-items-center gap-2">
                <i class="ri-lightbulb-line text-warning"></i>
                <span>Klik task di daftar atau pada timeline bar hijau untuk membuka rincian dan mengubah tanggal.</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="d-flex align-items-center gap-1">
                    <span class="badge rounded-circle bg-primary p-1"></span> Garis Biru = Hari Ini
                </span>
                <span class="d-flex align-items-center gap-1">
                    <span class="badge rounded-circle bg-success p-1"></span> Bar Hijau = Durasi Task
                </span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.gantt-container {
    background-color: var(--bs-body-bg, #0f172a);
}

.gantt-header {
    background-color: var(--bs-tertiary-bg, #1e293b);
}

.gantt-left-pane {
    width: 340px;
    background-color: var(--bs-body-bg, #0f172a);
    z-index: 2;
}

.gantt-row {
    height: 44px;
}

.gantt-table-header {
    height: 38px;
    background-color: var(--bs-tertiary-bg, #1e293b);
}

.gantt-task-item:hover {
    background-color: var(--bs-tertiary-bg, rgba(255, 255, 255, 0.04));
}

.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.gantt-right-pane {
    min-width: 600px;
}

.gantt-month-header {
    height: 28px;
    background-color: var(--bs-tertiary-bg, #1e293b);
}

.gantt-weeks-header {
    height: 38px;
    background-color: var(--bs-tertiary-bg, #1e293b);
}

.current-week-header {
    background-color: rgba(14, 165, 233, 0.08);
}

.current-week-bg {
    background-color: rgba(14, 165, 233, 0.03);
}

.today-vertical-line {
    width: 2px;
    background-color: #0284c7;
    z-index: 10;
}

.today-marker-dot {
    width: 8px;
    height: 8px;
    background-color: #0284c7;
    border-radius: 50%;
    position: absolute;
    top: -4px;
    left: -3px;
    box-shadow: 0 0 6px #0284c7;
}

.gantt-bar-row {
    background-color: transparent;
}

.gantt-bar-row:hover {
    background-color: rgba(255, 255, 255, 0.02);
}

.gantt-bar {
    height: 24px;
    z-index: 5;
    transition: transform 0.15s ease, filter 0.15s ease;
}

.gantt-bar:hover {
    transform: translateY(-1px);
    filter: brightness(1.15);
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
</style>

