<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Sortable from 'sortablejs';
import { confirmDelete } from '@/Utils/alert';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const activeTab = ref('kanban'); // 'kanban', 'overview', 'settings'

// Active List (default to first list)
const selectedListId = ref(props.space.lists?.[0]?.id || null);
const currentList = computed(() => {
    return props.space.lists?.find((l) => l.id === selectedListId.value) || props.space.lists?.[0] || null;
});

// Settings Form
const editForm = useForm({
    name: props.space.name,
    description: props.space.description || '',
    status: props.space.status,
    due_at: props.space.due_at || '',
});

const updateSpace = () => {
    editForm.put(`/spaces/${props.space.id}`);
};

// Quick Add Task State
const activeQuickAddStageId = ref(null);
const quickTaskTitle = ref('');
const quickTaskPriority = ref('medium');
const isSubmittingTask = ref(false);

const openQuickAdd = (stageId) => {
    activeQuickAddStageId.value = stageId;
    quickTaskTitle.value = '';
    quickTaskPriority.value = 'medium';
};

const cancelQuickAdd = () => {
    activeQuickAddStageId.value = null;
    quickTaskTitle.value = '';
};

const submitQuickTask = (stageId) => {
    if (!quickTaskTitle.value.trim() || isSubmittingTask.value) return;

    isSubmittingTask.value = true;
    router.post(
        '/tasks',
        {
            list_id: currentList.value.id,
            stage_id: stageId,
            title: quickTaskTitle.value.trim(),
            priority: quickTaskPriority.value,
        },
        {
            onSuccess: () => {
                cancelQuickAdd();
            },
            onFinish: () => {
                isSubmittingTask.value = false;
            },
        }
    );
};

// Delete Task
const deleteTask = async (task) => {
    const confirmed = await confirmDelete({
        title: 'Hapus Task?',
        text: `Yakin ingin menghapus task "${task.title}"?`,
        confirmButtonText: 'Ya, Hapus Task!',
    });
    if (confirmed) {
        if (selectedTask.value && selectedTask.value.id === task.id) {
            closeTaskModal();
        }
        router.delete(`/tasks/${task.id}`);
    }
};

// Priority Badges Helper
const priorityColor = (priority) => {
    switch (priority) {
        case 'urgent':
            return 'bg-danger text-white';
        case 'high':
            return 'bg-danger-subtle text-danger border border-danger-subtle';
        case 'medium':
            return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        default:
            return 'bg-success-subtle text-success border border-success-subtle';
    }
};

// Format Helpers
const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch {
        return dateStr;
    }
};

// ==========================================
// TASK DETAIL MODAL STATE & METHODS
// ==========================================
const isTaskModalOpen = ref(false);
const selectedTask = ref(null);
const taskModalTab = ref('details'); // 'details' or 'comments'
const taskActivityLogs = ref([]);
const isLoadingTaskDetails = ref(false);
const isSavingTask = ref(false);

// Edit Task Form Fields
const taskTitle = ref('');
const taskDescription = ref('');
const taskPriority = ref('medium');
const taskStageId = ref(null);
const taskDueAt = ref('');
const taskEstimateMinutes = ref('');
const taskAssigneeIds = ref([]);

// Checklist Subsystem
const newChecklistTitle = ref('');
const isSubmittingChecklist = ref(false);

// Attachment Subsystem
const isUploadingAttachment = ref(false);
const fileInputRef = ref(null);

// Comment Subsystem
const newCommentContent = ref('');
const isSubmittingComment = ref(false);

// Helper to get CSRF token
const getCsrfToken = () => {
    return page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content;
};

// Open Task Detail Modal
const openTaskModal = async (task) => {
    selectedTask.value = JSON.parse(JSON.stringify(task));
    taskTitle.value = task.title || '';
    taskDescription.value = task.description || '';
    taskPriority.value = task.priority || 'medium';
    taskStageId.value = task.stage_id;
    taskDueAt.value = task.due_at || '';
    taskEstimateMinutes.value = task.estimate_minutes || '';
    taskAssigneeIds.value = task.assignees ? task.assignees.map((a) => a.id) : [];
    newChecklistTitle.value = '';
    newCommentContent.value = '';
    taskModalTab.value = 'details';
    taskActivityLogs.value = [];
    isTaskModalOpen.value = true;

    // Fetch fresh task data & activity logs
    isLoadingTaskDetails.value = true;
    try {
        const res = await fetch(`/tasks/${task.id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            const data = await res.json();
            if (data.task) {
                selectedTask.value = data.task;
                taskActivityLogs.value = data.activity_logs || [];
            }
        }
    } catch (err) {
        console.error('Error fetching task details', err);
    } finally {
        isLoadingTaskDetails.value = false;
    }
};

const closeTaskModal = () => {
    isTaskModalOpen.value = false;
    selectedTask.value = null;
};

// Save Task Attributes
const saveTaskDetails = async () => {
    if (!selectedTask.value || isSavingTask.value) return;

    isSavingTask.value = true;
    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                title: taskTitle.value,
                description: taskDescription.value,
                priority: taskPriority.value,
                stage_id: taskStageId.value,
                due_at: taskDueAt.value || null,
                estimate_minutes: taskEstimateMinutes.value ? parseInt(taskEstimateMinutes.value) : null,
                assignee_ids: taskAssigneeIds.value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.task) {
                selectedTask.value = data.task;
            }
            // Reload space data to synchronize Kanban cards
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error saving task', err);
    } finally {
        isSavingTask.value = false;
    }
};

// Checklist Handlers
const addChecklist = async () => {
    if (!newChecklistTitle.value.trim() || isSubmittingChecklist.value || !selectedTask.value) return;

    isSubmittingChecklist.value = true;
    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/checklists`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                title: newChecklistTitle.value.trim(),
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (!selectedTask.value.checklists) {
                selectedTask.value.checklists = [];
            }
            selectedTask.value.checklists.push(data.checklist);
            newChecklistTitle.value = '';
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error adding checklist', err);
    } finally {
        isSubmittingChecklist.value = false;
    }
};

const toggleChecklist = async (chk) => {
    if (!selectedTask.value) return;

    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/checklists/${chk.id}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
        });

        if (res.ok) {
            const data = await res.json();
            chk.is_completed = data.checklist.is_completed;
            chk.completed_by = data.checklist.completed_by;
            chk.completed_at = data.checklist.completed_at;
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error toggling checklist', err);
    }
};

const deleteChecklist = async (chk) => {
    if (!selectedTask.value) return;

    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/checklists/${chk.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
        });

        if (res.ok) {
            selectedTask.value.checklists = selectedTask.value.checklists.filter((c) => c.id !== chk.id);
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error deleting checklist', err);
    }
};

// Checklist Completion Calculation
const checklistProgress = computed(() => {
    if (!selectedTask.value?.checklists?.length) return 0;
    const completed = selectedTask.value.checklists.filter((c) => c.is_completed).length;
    return Math.round((completed / selectedTask.value.checklists.length) * 100);
});

// Attachment Handlers
const triggerFileInput = () => {
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const handleFileUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file || !selectedTask.value) return;

    isUploadingAttachment.value = true;
    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/attachments`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: formData,
        });

        if (res.ok) {
            const data = await res.json();
            if (!selectedTask.value.attachments) {
                selectedTask.value.attachments = [];
            }
            selectedTask.value.attachments.unshift(data.attachment);
            if (fileInputRef.value) fileInputRef.value.value = '';
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error uploading attachment', err);
    } finally {
        isUploadingAttachment.value = false;
    }
};

const downloadAttachment = (att) => {
    if (!selectedTask.value) return;
    window.location.href = `/tasks/${selectedTask.value.id}/attachments/${att.id}/download`;
};

const deleteAttachment = async (att) => {
    const confirmed = await confirmDelete({
        title: 'Hapus Lampiran?',
        text: `Yakin ingin menghapus lampiran "${att.original_name}"?`,
        confirmButtonText: 'Ya, Hapus Berkas!',
    });
    if (!confirmed) return;

    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/attachments/${att.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
        });

        if (res.ok) {
            selectedTask.value.attachments = selectedTask.value.attachments.filter((a) => a.id !== att.id);
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error deleting attachment', err);
    }
};

// Comment Handlers
const addComment = async () => {
    if (!newCommentContent.value.trim() || isSubmittingComment.value || !selectedTask.value) return;

    isSubmittingComment.value = true;
    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/comments`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                content: newCommentContent.value.trim(),
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (!selectedTask.value.comments) {
                selectedTask.value.comments = [];
            }
            selectedTask.value.comments.unshift(data.comment);
            newCommentContent.value = '';
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error adding comment', err);
    } finally {
        isSubmittingComment.value = false;
    }
};

const deleteComment = async (com) => {
    const confirmed = await confirmDelete({
        title: 'Hapus Komentar?',
        text: 'Yakin ingin menghapus komentar ini?',
        confirmButtonText: 'Ya, Hapus Komentar!',
    });
    if (!confirmed) return;

    try {
        const res = await fetch(`/tasks/${selectedTask.value.id}/comments/${com.id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
        });

        if (res.ok) {
            selectedTask.value.comments = selectedTask.value.comments.filter((c) => c.id !== com.id);
            router.reload({ only: ['space'] });
        }
    } catch (err) {
        console.error('Error deleting comment', err);
    }
};

// ==========================================
// SORTABLE KANBAN DRAG-AND-DROP
// ==========================================
let sortableInstances = [];

const initSortable = () => {
    // Destroy previous instances
    sortableInstances.forEach((inst) => inst.destroy());
    sortableInstances = [];

    const columnElements = document.querySelectorAll('.kanban-cards-container');
    columnElements.forEach((colEl) => {
        const sortable = new Sortable(colEl, {
            group: 'kanban-tasks',
            animation: 200,
            ghostClass: 'kanban-ghost-card',
            chosenClass: 'kanban-chosen-card',
            dragClass: 'kanban-drag-card',
            fallbackTolerance: 3,
            onEnd: async (evt) => {
                const taskId = evt.item.dataset.taskId;
                const newStageId = evt.to.dataset.stageId;
                const newPosition = evt.newIndex + 1;

                if (!taskId || !newStageId) return;

                // Send AJAX request in background (Optimistic update)
                try {
                    const response = await fetch(`/tasks/${taskId}/move`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            stage_id: parseInt(newStageId),
                            position: newPosition,
                        }),
                    });

                    if (!response.ok) {
                        console.error('Gagal memindahkan task');
                        router.reload({ only: ['space'] });
                    }
                } catch (err) {
                    console.error('Koneksi terputus saat memindahkan task', err);
                    router.reload({ only: ['space'] });
                }
            },
        });
        sortableInstances.push(sortable);
    });
};

onMounted(() => {
    nextTick(() => {
        initSortable();
    });
});

watch(
    () => props.space,
    () => {
        nextTick(() => {
            initSortable();
        });
    },
    { deep: true }
);

watch(selectedListId, () => {
    nextTick(() => {
        initSortable();
    });
});

// ==========================================
// LIST VIEW / TABLE VIEW FILTERS
// ==========================================
const listSearchQuery = ref('');
const listPriorityFilter = ref('all');
const listStageFilter = ref('all');

const allCurrentTasks = computed(() => {
    if (!currentList.value?.stages) return [];
    return currentList.value.stages.flatMap((stg) =>
        (stg.tasks || []).map((t) => ({ ...t, stage_name: stg.name, stage_color: stg.color }))
    );
});

const filteredTasks = computed(() => {
    return allCurrentTasks.value.filter((t) => {
        const matchesQuery = !listSearchQuery.value.trim() ||
            t.title.toLowerCase().includes(listSearchQuery.value.toLowerCase()) ||
            (t.description && t.description.toLowerCase().includes(listSearchQuery.value.toLowerCase()));

        const matchesPriority = listPriorityFilter.value === 'all' || t.priority === listPriorityFilter.value;
        const matchesStage = listStageFilter.value === 'all' || t.stage_id == listStageFilter.value;

        return matchesQuery && matchesPriority && matchesStage;
    });
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`${space.name} - Papan Proyek`" />

        <!-- Space Top Bar Header -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <Link href="/spaces" class="text-muted text-decoration-none fs-13">
                                <i class="ri-arrow-left-line me-1"></i> Kembali ke Spaces
                            </Link>
                            <span class="text-muted">/</span>
                            <span class="badge bg-primary-subtle text-primary fs-11">{{ space.workspace?.name }}</span>
                        </div>
                        <h4 class="fw-bold mb-1 d-flex align-items-center gap-2">
                            <span>{{ space.name }}</span>
                            <span
                                class="badge rounded-pill px-2 py-1 fs-11"
                                :class="space.status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary'"
                            >
                                {{ space.status === 'active' ? 'Aktif' : 'Selesai' }}
                            </span>
                        </h4>
                        <p class="text-muted fs-13 mb-0" style="max-width: 650px;">
                            {{ space.description || 'Ruang kerja kolaboratif tim.' }}
                        </p>
                    </div>

                    <!-- Right Meta: Members & Deadline -->
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div v-if="space.due_at" class="bg-light border rounded-pill px-3 py-1 fs-12 text-muted">
                            <i class="ri-calendar-line me-1 text-primary"></i> Tenggat: <span class="fw-semibold text-body">{{ space.due_at }}</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div
                                v-for="(member, idx) in space.members"
                                :key="member.id"
                                class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold fs-11 border border-2 border-white shadow-sm"
                                style="width: 32px; height: 32px; margin-left: -8px;"
                                :title="member.name"
                            >
                                {{ member.name.charAt(0).toUpperCase() }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs border-bottom-0 mt-4 mb-0">
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link py-2 px-3 fw-semibold fs-13 border-0"
                            :class="{ 'active border-bottom border-primary border-3 text-primary': activeTab === 'kanban' }"
                            @click="activeTab = 'kanban'"
                        >
                            <i class="ri-kanban-view-2 me-1"></i> Papan Kanban
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link py-2 px-3 fw-semibold fs-13 border-0"
                            :class="{ 'active border-bottom border-primary border-3 text-primary': activeTab === 'list' }"
                            @click="activeTab = 'list'"
                        >
                            <i class="ri-list-check-2 me-1"></i> Tampilan Tabel (List View)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link py-2 px-3 fw-semibold fs-13 border-0"
                            :class="{ 'active border-bottom border-primary border-3 text-primary': activeTab === 'overview' }"
                            @click="activeTab = 'overview'"
                        >
                            <i class="ri-information-line me-1"></i> Ringkasan & Anggota
                        </button>
                    </li>
                    <li class="nav-item">
                        <button
                            type="button"
                            class="nav-link py-2 px-3 fw-semibold fs-13 border-0"
                            :class="{ 'active border-bottom border-primary border-3 text-primary': activeTab === 'settings' }"
                            @click="activeTab = 'settings'"
                        >
                            <i class="ri-settings-3-line me-1"></i> Pengaturan Space
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- TAB 1: KANBAN BOARD -->
        <div v-if="activeTab === 'kanban'">
            <!-- List Selector (if multiple lists exist) -->
            <div v-if="space.lists?.length > 1" class="d-flex align-items-center gap-2 mb-3">
                <span class="fs-12 text-muted fw-semibold">Pilih List:</span>
                <div class="btn-group btn-group-sm">
                    <button
                        v-for="lst in space.lists"
                        :key="lst.id"
                        type="button"
                        class="btn rounded-pill px-3 me-1"
                        :class="selectedListId === lst.id ? 'btn-primary' : 'btn-light border'"
                        @click="selectedListId = lst.id"
                    >
                        {{ lst.name }}
                    </button>
                </div>
            </div>

            <!-- Kanban Columns Container -->
            <div class="kanban-board-wrapper d-flex gap-3 overflow-auto pb-4" style="min-height: 540px;">
                <div
                    v-for="stage in currentList?.stages"
                    :key="stage.id"
                    class="kanban-column bg-light-subtle rounded-3 p-3 d-flex flex-column border border-secondary-subtle"
                    style="width: 295px; min-width: 295px;"
                >
                    <!-- Stage Header -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="rounded-circle d-inline-block" :style="{ width: '10px', height: '10px', backgroundColor: stage.color }"></span>
                            <h6 class="fw-bold mb-0 fs-13">{{ stage.name }}</h6>
                            <span class="badge bg-secondary-subtle text-secondary rounded-pill fs-11 px-2">
                                {{ stage.tasks?.length || 0 }}
                            </span>
                        </div>
                        <button
                            type="button"
                            class="btn btn-sm btn-light-light p-1 rounded-circle text-muted"
                            title="Tambah task ke tahapan ini"
                            @click="openQuickAdd(stage.id)"
                        >
                            <i class="ri-add-line fs-14"></i>
                        </button>
                    </div>

                    <!-- Quick Add Card Form -->
                    <div v-if="activeQuickAddStageId === stage.id" class="card border border-primary shadow-sm rounded-3 p-2 mb-2 bg-body">
                        <textarea
                            v-model="quickTaskTitle"
                            class="form-control form-control-sm border-0 mb-2 p-1"
                            rows="2"
                            placeholder="Ketik judul tugas baru..."
                            @keydown.enter.prevent="submitQuickTask(stage.id)"
                            autofocus
                        ></textarea>
                        <div class="d-flex justify-content-between align-items-center">
                            <select v-model="quickTaskPriority" class="form-select form-select-sm border-0 py-0 px-1 fs-11 w-auto text-muted">
                                <option value="low">🟢 Low</option>
                                <option value="medium">🟡 Medium</option>
                                <option value="high">🔴 High</option>
                                <option value="urgent">🟣 Urgent</option>
                            </select>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light py-1 px-2 fs-11" @click="cancelQuickAdd">Batal</button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-primary py-1 px-2 fs-11 fw-semibold"
                                    :disabled="!quickTaskTitle.trim() || isSubmittingTask"
                                    @click="submitQuickTask(stage.id)"
                                >
                                    Tambah
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tasks List in Column (Sortable Container) -->
                    <div
                        class="kanban-cards-container d-flex flex-column gap-2 flex-grow-1"
                        :data-stage-id="stage.id"
                        style="min-height: 80px;"
                    >
                        <div
                            v-for="task in stage.tasks"
                            :key="task.id"
                            :data-task-id="task.id"
                            class="kanban-card card border border-secondary-subtle shadow-sm rounded-3 p-3 bg-body cursor-pointer hover-shadow"
                            @click="openTaskModal(task)"
                        >
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge rounded-pill px-2 py-1 fs-10 text-uppercase" :class="priorityColor(task.priority)">
                                    {{ task.priority }}
                                </span>
                                <div class="dropdown" @click.stop>
                                    <button class="btn btn-sm btn-link text-muted p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ri-more-line"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border rounded-2 p-1 fs-12">
                                        <li>
                                            <button @click="openTaskModal(task)" type="button" class="dropdown-item d-flex align-items-center gap-2 py-1">
                                                <i class="ri-edit-line"></i>
                                                <span>Detail Task</span>
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button @click="deleteTask(task)" type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 py-1">
                                                <i class="ri-delete-bin-line"></i>
                                                <span>Hapus Task</span>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <h6 class="fw-bold fs-13 mb-2 lh-base text-body">{{ task.title }}</h6>

                            <p v-if="task.description" class="fs-11 text-muted mb-2 text-truncate-2">
                                {{ task.description }}
                            </p>

                            <!-- Task Footer Meta -->
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-1 fs-11 text-muted">
                                <div class="d-flex align-items-center gap-2">
                                    <span v-if="task.checklists?.length" class="d-flex align-items-center gap-1">
                                        <i class="ri-checkbox-line text-primary"></i>
                                        {{ task.checklists.filter((c) => c.is_completed).length }}/{{ task.checklists.length }}
                                    </span>
                                    <span v-if="task.attachments?.length" class="d-flex align-items-center gap-1">
                                        <i class="ri-attachment-2"></i>
                                        {{ task.attachments.length }}
                                    </span>
                                    <span v-if="task.comments?.length" class="d-flex align-items-center gap-1">
                                        <i class="ri-chat-3-line"></i>
                                        {{ task.comments.length }}
                                    </span>
                                    <span v-if="task.due_at" class="text-muted">
                                        <i class="ri-calendar-line me-1"></i> {{ task.due_at }}
                                    </span>
                                </div>
                                <div class="d-flex align-items-center">
                                    <div
                                        v-for="assignee in task.assignees"
                                        :key="assignee.id"
                                        class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-semibold fs-10"
                                        style="width: 22px; height: 22px; margin-left: -4px;"
                                        :title="assignee.name"
                                    >
                                        {{ assignee.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Add Task Link Button at Bottom of Column -->
                        <button
                            v-if="activeQuickAddStageId !== stage.id"
                            type="button"
                            class="btn btn-sm btn-light border-dashed w-100 text-muted fs-12 mt-1 py-1"
                            @click="openQuickAdd(stage.id)"
                        >
                            <i class="ri-add-line me-1"></i> Tambah Task
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB: LIST VIEW / TABLE VIEW -->
        <div v-else-if="activeTab === 'list'" class="card border border-secondary-subtle rounded-3 shadow-sm overflow-hidden mb-4">
            <!-- Table Filter Toolbar -->
            <div class="card-header bg-body p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 600px;">
                    <div class="input-group input-group-sm rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-transparent border-0 pe-1">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input
                            v-model="listSearchQuery"
                            type="text"
                            class="form-control border-0 ps-1 fs-12"
                            placeholder="Cari task berdasarkan judul atau deskripsi..."
                        />
                        <button
                            v-if="listSearchQuery"
                            type="button"
                            class="btn btn-link btn-sm text-muted p-0 pe-2 border-0"
                            @click="listSearchQuery = ''"
                        >
                            <i class="ri-close-line"></i>
                        </button>
                    </div>

                    <!-- Priority Filter -->
                    <select v-model="listPriorityFilter" class="form-select form-select-sm rounded-pill fs-12 w-auto">
                        <option value="all">Semua Prioritas</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>

                    <!-- Stage Filter -->
                    <select v-model="listStageFilter" class="form-select form-select-sm rounded-pill fs-12 w-auto">
                        <option value="all">Semua Tahapan</option>
                        <option v-for="stg in currentList?.stages" :key="stg.id" :value="stg.id">
                            {{ stg.name }}
                        </option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span class="fs-12 text-muted fw-semibold">
                        Menampilkan {{ filteredTasks.length }} dari {{ allCurrentTasks.length }} Task
                    </span>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold fs-12"
                        @click="openQuickAdd(currentList?.stages?.[0]?.id)"
                    >
                        <i class="ri-add-line me-1"></i> Tambah Task
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-11 text-muted text-uppercase">
                        <tr>
                            <th class="ps-4">Judul Tugas</th>
                            <th>Tahapan / Status</th>
                            <th>Prioritas</th>
                            <th>Penanggung Jawab</th>
                            <th>Subtask Checklist</th>
                            <th>Lampiran & Diskusi</th>
                            <th>Tenggat Waktu</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="task in filteredTasks"
                            :key="task.id"
                            class="cursor-pointer hover-bg-light"
                            @click="openTaskModal(task)"
                        >
                            <td class="ps-4">
                                <div class="fw-semibold fs-13 text-body">{{ task.title }}</div>
                                <div v-if="task.description" class="fs-11 text-muted text-truncate" style="max-width: 280px;">
                                    {{ task.description }}
                                </div>
                            </td>
                            <td>
                                <span
                                    class="badge rounded-pill px-2 py-1 fs-11"
                                    :style="{ backgroundColor: task.stage_color + '20', color: task.stage_color, border: '1px solid ' + task.stage_color }"
                                >
                                    {{ task.stage_name }}
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-2 py-1 fs-10 text-uppercase" :class="priorityColor(task.priority)">
                                    {{ task.priority }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div
                                        v-for="assignee in task.assignees"
                                        :key="assignee.id"
                                        class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-semibold fs-10 border border-white"
                                        style="width: 24px; height: 24px; margin-left: -5px;"
                                        :title="assignee.name"
                                    >
                                        {{ assignee.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span v-if="!task.assignees?.length" class="text-muted fs-11 italic">-</span>
                                </div>
                            </td>
                            <td>
                                <div v-if="task.checklists?.length" class="d-flex align-items-center gap-2" style="min-width: 110px;">
                                    <div class="progress flex-grow-1" style="height: 5px;">
                                        <div
                                            class="progress-bar bg-success"
                                            :style="{ width: `${Math.round((task.checklists.filter((c) => c.is_completed).length / task.checklists.length) * 100)}%` }"
                                        ></div>
                                    </div>
                                    <span class="fs-11 text-muted fw-semibold">
                                        {{ task.checklists.filter((c) => c.is_completed).length }}/{{ task.checklists.length }}
                                    </span>
                                </div>
                                <span v-else class="text-muted fs-11">-</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-2 fs-12 text-muted">
                                    <span v-if="task.attachments?.length" class="d-flex align-items-center gap-1" title="Lampiran berkas">
                                        <i class="ri-attachment-2"></i> {{ task.attachments.length }}
                                    </span>
                                    <span v-if="task.comments?.length" class="d-flex align-items-center gap-1" title="Komentar diskusi">
                                        <i class="ri-chat-3-line"></i> {{ task.comments.length }}
                                    </span>
                                    <span v-if="!task.attachments?.length && !task.comments?.length">-</span>
                                </div>
                            </td>
                            <td>
                                <span v-if="task.due_at" class="fs-12 text-muted">
                                    <i class="ri-calendar-line me-1"></i> {{ task.due_at }}
                                </span>
                                <span v-else class="text-muted fs-11">-</span>
                            </td>
                            <td class="text-end pe-4" @click.stop>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light p-1 rounded-circle" type="button" data-bs-toggle="dropdown">
                                        <i class="ri-more-line"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border rounded-2 p-1 fs-12">
                                        <li>
                                            <button @click="openTaskModal(task)" type="button" class="dropdown-item d-flex align-items-center gap-2 py-1">
                                                <i class="ri-edit-line"></i>
                                                <span>Detail Task</span>
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button @click="deleteTask(task)" type="button" class="dropdown-item text-danger d-flex align-items-center gap-2 py-1">
                                                <i class="ri-delete-bin-line"></i>
                                                <span>Hapus Task</span>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!filteredTasks.length">
                            <td colspan="8" class="text-center py-5 text-muted fs-13">
                                <i class="ri-file-search-line fs-32 d-block mb-2 text-secondary"></i>
                                Tidak ada task yang sesuai dengan kriteria pencarian atau filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: OVERVIEW & MEMBERS -->
        <div v-else-if="activeTab === 'overview'" class="card border border-secondary-subtle rounded-3 p-4 shadow-sm">
            <h5 class="fw-bold mb-3 fs-16">Anggota Tim Space</h5>
            <div class="row g-3">
                <div v-for="member in space.members" :key="member.id" class="col-md-4">
                    <div class="border rounded-3 p-3 d-flex align-items-center gap-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fs-16 fw-semibold" style="width: 42px; height: 42px;">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 fs-14">{{ member.name }}</h6>
                            <span class="fs-12 text-muted">{{ member.email }}</span>
                            <div class="badge bg-primary-subtle text-primary rounded-pill mt-1 fs-10 text-capitalize">
                                {{ member.pivot?.role || 'Member' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SETTINGS -->
        <div v-else-if="activeTab === 'settings'" class="card border border-secondary-subtle rounded-3 p-4 shadow-sm" style="max-width: 600px;">
            <h5 class="fw-bold mb-3 fs-16">Pengaturan Space</h5>
            <form @submit.prevent="updateSpace">
                <div class="mb-3">
                    <label class="form-label fs-13 fw-semibold">Nama Space</label>
                    <input v-model="editForm.name" type="text" class="form-control" required />
                </div>
                <div class="mb-3">
                    <label class="form-label fs-13 fw-semibold">Deskripsi</label>
                    <textarea v-model="editForm.description" class="form-control" rows="3"></textarea>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fs-13 fw-semibold">Status</label>
                        <select v-model="editForm.status" class="form-select">
                            <option value="active">Aktif</option>
                            <option value="completed">Selesai</option>
                            <option value="archived">Diarsipkan</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fs-13 fw-semibold">Tenggat Waktu</label>
                        <input v-model="editForm.due_at" type="date" class="form-control" />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" :disabled="editForm.processing">
                    Simpan Perubahan
                </button>
            </form>
        </div>

        <!-- ============================================================ -->
        <!-- TASK DETAIL MODAL (CLICKUP STYLE MODAL DRAWER)               -->
        <!-- ============================================================ -->
        <div v-if="isTaskModalOpen" class="modal-backdrop fade show" style="z-index: 1050;" @click="closeTaskModal"></div>

        <div
            v-if="isTaskModalOpen"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="z-index: 1055;"
        >
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content rounded-3 border-0 shadow-lg">
                    <!-- Modal Header -->
                    <div class="modal-header border-bottom px-4 py-3 bg-light-subtle">
                        <div class="d-flex align-items-center gap-2 flex-grow-1 me-3">
                            <span class="badge rounded-pill px-2 py-1 fs-11 text-uppercase" :class="priorityColor(taskPriority)">
                                {{ taskPriority }}
                            </span>
                            <input
                                v-model="taskTitle"
                                type="text"
                                class="form-control form-control-lg border-0 bg-transparent fw-bold fs-16 p-0 text-body"
                                placeholder="Judul task..."
                                @blur="saveTaskDetails"
                            />
                        </div>
                        <button type="button" class="btn-close" @click="closeTaskModal"></button>
                    </div>

                    <!-- Modal Body (Two-column layout) -->
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <!-- Left Column: Description, Checklists, Attachments, Comments -->
                            <div class="col-lg-8">
                                <!-- Tabs Navigation inside modal -->
                                <ul class="nav nav-pills mb-4 bg-light p-1 rounded-pill w-fit border">
                                    <li class="nav-item">
                                        <button
                                            type="button"
                                            class="nav-link rounded-pill py-1 px-3 fs-12 fw-semibold"
                                            :class="{ 'active bg-primary text-white': taskModalTab === 'details' }"
                                            @click="taskModalTab = 'details'"
                                        >
                                            <i class="ri-file-list-3-line me-1"></i> Rincian & Checklist
                                        </button>
                                    </li>
                                    <li class="nav-item">
                                        <button
                                            type="button"
                                            class="nav-link rounded-pill py-1 px-3 fs-12 fw-semibold"
                                            :class="{ 'active bg-primary text-white': taskModalTab === 'comments' }"
                                            @click="taskModalTab = 'comments'"
                                        >
                                            <i class="ri-chat-3-line me-1"></i> Diskusi & Log
                                            <span v-if="selectedTask?.comments?.length" class="badge bg-light text-dark rounded-pill ms-1">
                                                {{ selectedTask.comments.length }}
                                            </span>
                                        </button>
                                    </li>
                                </ul>

                                <!-- TAB CONTENT: DETAILS (Description, Checklist, Attachments) -->
                                <div v-if="taskModalTab === 'details'">
                                    <!-- Description -->
                                    <div class="mb-4">
                                        <label class="form-label fs-13 fw-bold text-muted text-uppercase mb-2">
                                            <i class="ri-align-left me-1"></i> Deskripsi Task
                                        </label>
                                        <textarea
                                            v-model="taskDescription"
                                            class="form-control rounded-3 fs-13"
                                            rows="4"
                                            placeholder="Tambahkan rincian instruksi, catatan kriteria selesai, atau link dokumen..."
                                            @blur="saveTaskDetails"
                                        ></textarea>
                                    </div>

                                    <!-- Checklists Subsystem -->
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fs-13 fw-bold text-muted text-uppercase mb-0">
                                                <i class="ri-checkbox-line me-1"></i> Checklist Subtask
                                            </label>
                                            <span class="fs-12 text-muted fw-semibold">
                                                {{ selectedTask?.checklists?.filter((c) => c.is_completed).length || 0 }} / {{ selectedTask?.checklists?.length || 0 }} ({{ checklistProgress }}%)
                                            </span>
                                        </div>

                                        <!-- Progress Bar -->
                                        <div class="progress mb-3" style="height: 6px;">
                                            <div
                                                class="progress-bar bg-success"
                                                role="progressbar"
                                                :style="{ width: `${checklistProgress}%` }"
                                            ></div>
                                        </div>

                                        <!-- Checklists Items List -->
                                        <div class="d-flex flex-column gap-2 mb-3">
                                            <div
                                                v-for="chk in selectedTask?.checklists"
                                                :key="chk.id"
                                                class="d-flex align-items-center justify-content-between p-2 rounded-2 border bg-light-subtle"
                                            >
                                                <div class="d-flex align-items-center gap-2 flex-grow-1">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input mt-0 cursor-pointer"
                                                        :checked="chk.is_completed"
                                                        @change="toggleChecklist(chk)"
                                                    />
                                                    <span
                                                        class="fs-13 text-body"
                                                        :class="{ 'text-decoration-line-through text-muted': chk.is_completed }"
                                                    >
                                                        {{ chk.title }}
                                                    </span>
                                                </div>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-link text-danger p-0 border-0"
                                                    title="Hapus checklist item"
                                                    @click="deleteChecklist(chk)"
                                                >
                                                    <i class="ri-delete-bin-line fs-14"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Add New Checklist Item Form -->
                                        <form @submit.prevent="addChecklist" class="d-flex gap-2">
                                            <input
                                                v-model="newChecklistTitle"
                                                type="text"
                                                class="form-control form-control-sm rounded-pill px-3 fs-13"
                                                placeholder="Tambah item checklist baru..."
                                                :disabled="isSubmittingChecklist"
                                            />
                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-primary rounded-pill px-3 fs-13 fw-semibold text-nowrap"
                                                :disabled="!newChecklistTitle.trim() || isSubmittingChecklist"
                                            >
                                                <i class="ri-add-line me-1"></i> Tambah
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Attachments Subsystem -->
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fs-13 fw-bold text-muted text-uppercase mb-0">
                                                <i class="ri-attachment-2 me-1"></i> Lampiran Berkas
                                            </label>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-light border rounded-pill px-3 fs-12 fw-semibold"
                                                :disabled="isUploadingAttachment"
                                                @click="triggerFileInput"
                                            >
                                                <i class="ri-upload-2-line me-1"></i> Unggah Berkas
                                            </button>
                                            <input
                                                ref="fileInputRef"
                                                type="file"
                                                class="d-none"
                                                @change="handleFileUpload"
                                            />
                                        </div>

                                        <!-- Attachments List -->
                                        <div v-if="selectedTask?.attachments?.length" class="row g-2">
                                            <div
                                                v-for="att in selectedTask.attachments"
                                                :key="att.id"
                                                class="col-md-6"
                                            >
                                                <div class="border rounded-3 p-2 bg-light-subtle d-flex align-items-center justify-content-between">
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                        <i class="ri-file-text-line fs-20 text-primary flex-shrink-0"></i>
                                                        <div class="overflow-hidden">
                                                            <div class="fw-semibold fs-12 text-truncate" :title="att.original_name">
                                                                {{ att.original_name }}
                                                            </div>
                                                            <div class="fs-10 text-muted">
                                                                {{ formatFileSize(att.size_bytes) }} • {{ att.uploader?.name || 'User' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-light p-1 rounded-circle"
                                                            title="Unduh Berkas"
                                                            @click="downloadAttachment(att)"
                                                        >
                                                            <i class="ri-download-2-line fs-14"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-light p-1 rounded-circle text-danger"
                                                            title="Hapus Berkas"
                                                            @click="deleteAttachment(att)"
                                                        >
                                                            <i class="ri-delete-bin-line fs-14"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-muted fs-12 border border-dashed rounded-3 p-3 text-center">
                                            Belum ada berkas terlampir. Klik tombol 'Unggah Berkas' di atas.
                                        </div>
                                    </div>
                                </div>

                                <!-- TAB CONTENT: COMMENTS & AUDIT TRAIL -->
                                <div v-else-if="taskModalTab === 'comments'">
                                    <!-- Add Comment Form -->
                                    <div class="card border rounded-3 p-3 mb-4 bg-light-subtle">
                                        <label class="form-label fs-13 fw-bold mb-2">Tulis Komentar Diskusi</label>
                                        <textarea
                                            v-model="newCommentContent"
                                            class="form-control fs-13 mb-2"
                                            rows="3"
                                            placeholder="Tulis pesan atau update progres..."
                                            :disabled="isSubmittingComment"
                                        ></textarea>
                                        <div class="d-flex justify-content-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold fs-13"
                                                :disabled="!newCommentContent.trim() || isSubmittingComment"
                                                @click="addComment"
                                            >
                                                <i class="ri-send-plane-fill me-1"></i> Kirim Komentar
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Comments Stream -->
                                    <div class="mb-4">
                                        <h6 class="fw-bold fs-14 mb-3">Diskusi Tim ({{ selectedTask?.comments?.length || 0 }})</h6>
                                        <div v-if="selectedTask?.comments?.length" class="d-flex flex-column gap-3">
                                            <div
                                                v-for="com in selectedTask.comments"
                                                :key="com.id"
                                                class="border rounded-3 p-3 bg-body shadow-2xs"
                                            >
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-semibold fs-11" style="width: 28px; height: 28px;">
                                                            {{ com.user?.name?.charAt(0).toUpperCase() || 'U' }}
                                                        </div>
                                                        <span class="fw-bold fs-13 text-body">{{ com.user?.name || 'User' }}</span>
                                                        <span class="fs-11 text-muted">{{ formatDate(com.created_at) }}</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-link text-danger p-0 border-0"
                                                        title="Hapus komentar"
                                                        @click="deleteComment(com)"
                                                    >
                                                        <i class="ri-delete-bin-line fs-14"></i>
                                                    </button>
                                                </div>
                                                <p class="fs-13 text-body mb-0 lh-base text-break">
                                                    {{ com.content }}
                                                </p>
                                            </div>
                                        </div>
                                        <div v-else class="text-muted fs-12 border border-dashed rounded-3 p-3 text-center">
                                            Belum ada diskusi untuk task ini.
                                        </div>
                                    </div>

                                    <!-- Activity Log Audit Trail -->
                                    <div class="mt-4 pt-3 border-top">
                                        <h6 class="fw-bold fs-14 mb-3 d-flex align-items-center gap-2">
                                            <i class="ri-history-line text-primary"></i>
                                            <span>Riwayat Aktivitas (Audit Trail)</span>
                                        </h6>
                                        <div v-if="taskActivityLogs.length" class="timeline d-flex flex-column gap-2">
                                            <div
                                                v-for="act in taskActivityLogs"
                                                :key="act.id"
                                                class="d-flex align-items-start gap-2 fs-12 text-muted p-2 rounded-2 bg-light-subtle"
                                            >
                                                <i class="ri-checkbox-circle-fill text-success fs-14 mt-1"></i>
                                                <div class="flex-grow-1">
                                                    <span class="fw-semibold text-body">{{ act.actor?.name || 'Seseorang' }}</span>
                                                    <span class="ms-1">melakukan aksi <code>{{ act.action }}</code></span>
                                                    <div v-if="act.metadata" class="fs-11 text-muted mt-0">
                                                        <span v-if="act.metadata.new_stage">Pindah ke: <strong>{{ act.metadata.new_stage }}</strong></span>
                                                        <span v-else-if="act.metadata.new_priority">Prioritas diubah ke: <strong>{{ act.metadata.new_priority }}</strong></span>
                                                        <span v-else-if="act.metadata.checklist_title">Checklist: {{ act.metadata.checklist_title }}</span>
                                                        <span v-else-if="act.metadata.file_name">Berkas: {{ act.metadata.file_name }}</span>
                                                    </div>
                                                </div>
                                                <span class="fs-10 text-muted">{{ formatDate(act.created_at) }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-muted fs-11">
                                            Tidak ada riwayat aktivitas yang tercatat.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Sidebar Attributes -->
                            <div class="col-lg-4 border-start">
                                <div class="d-flex flex-column gap-3">
                                    <!-- Stage / Status -->
                                    <div>
                                        <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Status / Tahapan</label>
                                        <select
                                            v-model="taskStageId"
                                            class="form-select form-select-sm rounded-2"
                                            @change="saveTaskDetails"
                                        >
                                            <option v-for="stg in currentList?.stages" :key="stg.id" :value="stg.id">
                                                {{ stg.name }}
                                            </option>
                                        </select>
                                    </div>

                                    <!-- Priority -->
                                    <div>
                                        <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Prioritas</label>
                                        <select
                                            v-model="taskPriority"
                                            class="form-select form-select-sm rounded-2"
                                            @change="saveTaskDetails"
                                        >
                                            <option value="low">🟢 Low</option>
                                            <option value="medium">🟡 Medium</option>
                                            <option value="high">🔴 High</option>
                                            <option value="urgent">🟣 Urgent</option>
                                        </select>
                                    </div>

                                    <!-- Due Date -->
                                    <div>
                                        <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Tenggat Waktu</label>
                                        <input
                                            v-model="taskDueAt"
                                            type="date"
                                            class="form-control form-control-sm rounded-2"
                                            @change="saveTaskDetails"
                                        />
                                    </div>

                                    <!-- Estimated Minutes -->
                                    <div>
                                        <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Estimasi (Menit)</label>
                                        <input
                                            v-model="taskEstimateMinutes"
                                            type="number"
                                            min="0"
                                            class="form-control form-control-sm rounded-2"
                                            placeholder="Contoh: 120"
                                            @blur="saveTaskDetails"
                                        />
                                    </div>

                                    <!-- Assignees (Anggota Penanggung Jawab) -->
                                    <div>
                                        <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Penanggung Jawab (Assignees)</label>
                                        <div class="d-flex flex-column gap-2 p-2 rounded-2 border bg-light-subtle max-h-160 overflow-auto">
                                            <div
                                                v-for="member in space.members"
                                                :key="member.id"
                                                class="form-check d-flex align-items-center gap-2 mb-0"
                                            >
                                                <input
                                                    :id="`assignee-${member.id}`"
                                                    v-model="taskAssigneeIds"
                                                    type="checkbox"
                                                    class="form-check-input cursor-pointer"
                                                    :value="member.id"
                                                    @change="saveTaskDetails"
                                                />
                                                <label :for="`assignee-${member.id}`" class="form-check-label fs-12 cursor-pointer text-body">
                                                    {{ member.name }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-2" />

                                    <!-- Action Buttons -->
                                    <div class="d-flex flex-column gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-primary rounded-pill w-100 fw-semibold fs-13 py-2"
                                            :disabled="isSavingTask"
                                            @click="saveTaskDetails"
                                        >
                                            <i class="ri-save-line me-1"></i> Simpan Perubahan
                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-outline-danger rounded-pill w-100 fw-semibold fs-13 py-2"
                                            @click="deleteTask(selectedTask)"
                                        >
                                            <i class="ri-delete-bin-line me-1"></i> Hapus Task Ini
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.kanban-board-wrapper {
    display: flex;
    align-items: flex-start;
}
.cursor-pointer {
    cursor: pointer;
}
.cursor-grab {
    cursor: grab;
}
.cursor-grab:active {
    cursor: grabbing;
}
.hover-shadow:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    transform: translateY(-2px);
}
.border-dashed {
    border: 1px dashed var(--bs-border-color) !important;
}
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.w-fit {
    width: fit-content;
}
.max-h-160 {
    max-height: 160px;
}

/* Sortable Drag Styles */
.kanban-ghost-card {
    opacity: 0.4;
    background-color: var(--pe-primary-bg-subtle, rgba(0, 170, 173, 0.1)) !important;
    border: 2px dashed var(--pe-primary, #00aaad) !important;
}
.kanban-chosen-card {
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
}
.kanban-drag-card {
    transform: rotate(2deg);
    opacity: 0.95;
}
</style>
