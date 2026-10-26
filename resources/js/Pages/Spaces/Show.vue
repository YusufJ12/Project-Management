<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GanttTimeline from '@/Components/GanttTimeline.vue';
import Sortable from 'sortablejs';
import { confirmDelete, showToast } from '@/Utils/alert';
import { formatDate, formatDateShort, formatDueDate, formatDateTime, isOverdueDate } from '@/Utils/date';
import echo from '@/echo';

const props = defineProps({
    space: {
        type: Object,
        required: true,
    },
    workspaceMembers: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const activeTab = ref('kanban'); // 'kanban', 'overview', 'settings'

// RBAC: Check if current user is viewer
const currentUser = computed(() => page.props.auth?.user);
const currentSpaceMember = computed(() => {
    if (!currentUser.value || !localSpace.value?.members) return null;
    return localSpace.value.members.find((m) => m.id === currentUser.value.id);
});
const isViewer = computed(() => {
    if (currentUser.value?.is_super_admin) return false;
    if (currentSpaceMember.value?.pivot?.role === 'viewer') return true;
    if (page.props.auth?.currentWorkspace?.role === 'viewer') return true;
    if (currentUser.value?.role === 'viewer') return true;
    return false;
});

// Reactive copy of Space for smooth background real-time updates
const localSpace = ref(JSON.parse(JSON.stringify(props.space)));

watch(
    () => props.space,
    (newVal) => {
        if (newVal) {
            localSpace.value = JSON.parse(JSON.stringify(newVal));
        }
    },
    { deep: true }
);

// Active List (default to first list)
const selectedListId = ref(props.space.lists?.[0]?.id || null);
const currentList = computed(() => {
    return localSpace.value.lists?.find((l) => l.id === selectedListId.value) || localSpace.value.lists?.[0] || null;
});

// Settings Form
const editForm = useForm({
    name: props.space.name,
    description: props.space.description || '',
    status: props.space.status,
    due_at: props.space.due_at || '',
});

const updateSpace = () => {
    editForm.put(`/spaces/${props.space.id}`, {
        onSuccess: () => {
            showToast('Pengaturan Space berhasil diperbarui!', 'success');
        },
        onError: () => {
            showToast('Gagal memperbarui pengaturan Space.', 'error');
        },
    });
};

const deleteCurrentSpace = async () => {
    const confirmed = await confirmDelete({
        title: 'Hapus Space / Proyek?',
        text: `PERINGATAN: Menghapus Space "${props.space.name}" akan menghapus seluruh papan Kanban, task, komentar, dan lampiran berkas secara permanen.`,
        confirmButtonText: 'Ya, Hapus Permanen!',
    });
    if (confirmed) {
        router.delete(`/spaces/${props.space.id}`);
    }
};

// Space Member Management in Overview Tab
const showSpaceMemberModal = ref(false);
const isSpaceUserDropdownOpen = ref(false);
const spaceUserSearchQuery = ref('');

const addSpaceMemberForm = useForm({
    user_ids: [],
    role: 'member',
});

const availableWorkspaceUsers = computed(() => {
    const currentMemberIds = (localSpace.value.members || []).map((m) => m.id);
    return props.workspaceMembers.filter((u) => !currentMemberIds.includes(u.id));
});

const filteredAvailableWorkspaceUsers = computed(() => {
    if (!spaceUserSearchQuery.value.trim()) return availableWorkspaceUsers.value;
    const q = spaceUserSearchQuery.value.toLowerCase();
    return availableWorkspaceUsers.value.filter(
        (u) => u.name.toLowerCase().includes(q) || (u.email && u.email.toLowerCase().includes(q))
    );
});

const toggleSelectSpaceUser = (user) => {
    const idx = addSpaceMemberForm.user_ids.indexOf(user.id);
    if (idx > -1) {
        addSpaceMemberForm.user_ids.splice(idx, 1);
    } else {
        addSpaceMemberForm.user_ids.push(user.id);
    }
};

const selectedSpaceUserObjects = computed(() => {
    return props.workspaceMembers.filter((u) => addSpaceMemberForm.user_ids.includes(u.id));
});

const submitAddSpaceMember = () => {
    if (addSpaceMemberForm.user_ids.length === 0) return;

    addSpaceMemberForm.post(`/spaces/${props.space.id}/members`, {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Anggota berhasil ditambahkan ke Space!', 'success');
            addSpaceMemberForm.user_ids = [];
            isSpaceUserDropdownOpen.value = false;
        },
    });
};

const removeSpaceMember = async (member) => {
    const confirmed = await confirmDelete({
        title: 'Keluarkan dari Space?',
        text: `Yakin ingin mengeluarkan ${member.name} dari Space "${props.space.name}"? Pengguna tidak akan dapat melihat Space ini lagi.`,
        confirmButtonText: 'Ya, Keluarkan!',
    });
    if (confirmed) {
        router.delete(`/spaces/${props.space.id}/members/${member.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`${member.name} berhasil dikeluarkan dari Space.`, 'success');
            },
        });
    }
};

// ==========================================
// CUSTOM STAGES / STATUS MANAGEMENT
// ==========================================
const isAddingStage = ref(false);
const insertAfterStageId = ref(null);
const newStageName = ref('');
const newStageColor = ref('#6366f1');
const newStageIsDone = ref(false);
const isSubmittingStage = ref(false);

const stageColorPresets = [
    '#64748b', // Slate
    '#0284c7', // Sky Blue
    '#3b82f6', // Blue
    '#6366f1', // Indigo
    '#8b5cf6', // Violet
    '#ec4899', // Pink
    '#f97316', // Orange
    '#eab308', // Amber
    '#22c55e', // Emerald / Green
    '#14b8a6', // Teal
];

const openAddStageForm = (afterStageId = null) => {
    if (isViewer.value) return;
    newStageName.value = '';
    newStageColor.value = stageColorPresets[Math.floor(Math.random() * stageColorPresets.length)];
    newStageIsDone.value = false;
    insertAfterStageId.value = afterStageId;
    isAddingStage.value = true;
};

const cancelAddStage = () => {
    isAddingStage.value = false;
    insertAfterStageId.value = null;
    newStageName.value = '';
};

const submitCreateStage = async () => {
    if (isViewer.value) return;
    if (!newStageName.value.trim() || isSubmittingStage.value || !currentList.value) return;

    isSubmittingStage.value = true;
    try {
        const response = await fetch('/stages', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                list_id: currentList.value.id,
                name: newStageName.value.trim(),
                color: newStageColor.value,
                is_done: newStageIsDone.value,
                after_stage_id: insertAfterStageId.value,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            if (data.stage) {
                if (!currentList.value.stages) currentList.value.stages = [];
                const newStageItem = {
                    ...data.stage,
                    tasks: [],
                };

                if (insertAfterStageId.value) {
                    const idx = currentList.value.stages.findIndex((s) => s.id === insertAfterStageId.value);
                    if (idx !== -1) {
                        currentList.value.stages.splice(idx + 1, 0, newStageItem);
                    } else {
                        currentList.value.stages.push(newStageItem);
                    }
                } else {
                    currentList.value.stages.push(newStageItem);
                }

                showToast(data.message || 'Tahapan baru berhasil dibuat!', 'success');
                cancelAddStage();
                nextTick(() => {
                    initSortable();
                });
            }
        } else {
            showToast('Gagal menambahkan tahapan status.', 'error');
        }
    } catch (err) {
        console.error('Error creating stage', err);
        showToast('Koneksi terganggu saat membuat tahapan.', 'error');
    } finally {
        isSubmittingStage.value = false;
    }
};

const deleteStage = async (stage) => {
    if (isViewer.value) {
        showToast('Anda hanya memiliki akses Viewer (baca saja).', 'error');
        return;
    }
    const confirmed = await confirmDelete({
        title: 'Hapus Tahapan Status?',
        text: `Yakin ingin menghapus tahapan status "${stage.name}"? Tugas di dalamnya akan otomatis dialihkan ke tahapan lain.`,
        confirmButtonText: 'Ya, Hapus Tahapan!',
    });
    if (confirmed) {
        try {
            const response = await fetch(`/stages/${stage.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            });

            if (response.ok) {
                if (currentList.value?.stages) {
                    currentList.value.stages = currentList.value.stages.filter((s) => s.id !== stage.id);
                }
                showToast(`Tahapan "${stage.name}" berhasil dihapus.`, 'success');
                syncSpaceData();
            } else {
                showToast('Gagal menghapus tahapan status.', 'error');
            }
        } catch (err) {
            console.error('Error deleting stage', err);
        }
    }
};

// Quick Add Task State
const activeQuickAddStageId = ref(null);
const quickTaskTitle = ref('');
const quickTaskPriority = ref('medium');
const isSubmittingTask = ref(false);

const openQuickAdd = (stageId) => {
    if (isViewer.value) return;
    activeQuickAddStageId.value = stageId;
    quickTaskTitle.value = '';
    quickTaskPriority.value = 'medium';
};

const cancelQuickAdd = () => {
    activeQuickAddStageId.value = null;
    quickTaskTitle.value = '';
};

const submitQuickTask = (stageId) => {
    if (isViewer.value) return;
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
    if (isViewer.value) {
        showToast('Anda hanya memiliki akses Viewer (baca saja).', 'error');
        return;
    }
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
const taskStartDate = ref('');
const taskDueAt = ref('');
const taskEstimateMinutes = ref('');
const taskAssigneeIds = ref([]);
const taskDependencyIds = ref([]);
const selectedPrerequisiteTaskId = ref('');

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

// Modal clean snapshot to detect uncommitted user edits during realtime updates
const modalCleanSnapshot = ref(null);

const captureModalCleanSnapshot = (task) => {
    if (!task) {
        modalCleanSnapshot.value = null;
        return;
    }
    modalCleanSnapshot.value = {
        title: task.title || '',
        description: task.description || '',
        priority: task.priority || 'medium',
        stage_id: task.stage_id,
        start_date: task.start_date ? String(task.start_date).substring(0, 10) : '',
        due_at: task.due_at ? String(task.due_at).substring(0, 10) : '',
        estimate_minutes: task.estimate_minutes ? String(task.estimate_minutes) : '',
        assignee_ids: (task.assignees ? task.assignees.map((a) => a.id) : []).slice().sort().join(','),
        dependency_ids: (task.dependencies ? task.dependencies.map((d) => d.id) : []).slice().sort().join(','),
    };
};

const hasUncommittedEdits = () => {
    if (!modalCleanSnapshot.value) return false;
    const snap = modalCleanSnapshot.value;
    const currentAssignees = [...taskAssigneeIds.value].sort().join(',');
    const currentDeps = [...taskDependencyIds.value].sort().join(',');

    return (
        taskTitle.value !== snap.title ||
        taskDescription.value !== snap.description ||
        taskPriority.value !== snap.priority ||
        taskStageId.value !== snap.stage_id ||
        taskStartDate.value !== snap.start_date ||
        taskDueAt.value !== snap.due_at ||
        String(taskEstimateMinutes.value || '') !== snap.estimate_minutes ||
        currentAssignees !== snap.assignee_ids ||
        currentDeps !== snap.dependency_ids
    );
};

// Open Task Detail Modal
const openTaskModal = async (task) => {
    selectedTask.value = JSON.parse(JSON.stringify(task));
    taskTitle.value = task.title || '';
    taskDescription.value = task.description || '';
    taskPriority.value = task.priority || 'medium';
    taskStageId.value = task.stage_id;
    taskStartDate.value = task.start_date ? String(task.start_date).substring(0, 10) : '';
    taskDueAt.value = task.due_at ? String(task.due_at).substring(0, 10) : '';
    taskEstimateMinutes.value = task.estimate_minutes || '';
    taskAssigneeIds.value = task.assignees ? task.assignees.map((a) => a.id) : [];
    taskDependencyIds.value = task.dependencies ? task.dependencies.map((d) => d.id) : [];
    selectedPrerequisiteTaskId.value = '';
    newChecklistTitle.value = '';
    newCommentContent.value = '';
    taskModalTab.value = 'details';
    taskActivityLogs.value = [];
    isTaskModalOpen.value = true;
    captureModalCleanSnapshot(selectedTask.value);

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
                taskStartDate.value = data.task.start_date ? String(data.task.start_date).substring(0, 10) : '';
                taskDueAt.value = data.task.due_at ? String(data.task.due_at).substring(0, 10) : '';
                taskDependencyIds.value = data.task.dependencies ? data.task.dependencies.map((d) => d.id) : [];
                taskActivityLogs.value = data.activity_logs || [];
                syncTaskInLocalSpace(data.task);
                captureModalCleanSnapshot(data.task);
            }
        }
    } catch (err) {
        console.error('Error fetching task details', err);
    } finally {
        isLoadingTaskDetails.value = false;
    }
};

// Open Task by ID (e.g. from Notification deep-link)
const openTaskById = async (taskId, preferredTab = null) => {
    if (!taskId) return;
    const taskIdNum = parseInt(taskId);

    // Look for task in localSpace
    let foundTask = null;
    for (const list of localSpace.value?.lists || []) {
        for (const stage of list.stages || []) {
            const t = (stage.tasks || []).find((tk) => tk.id === taskIdNum);
            if (t) {
                foundTask = t;
                break;
            }
        }
        if (foundTask) break;
    }

    if (foundTask) {
        await openTaskModal(foundTask);
        if (preferredTab) {
            taskModalTab.value = preferredTab;
        }
    } else {
        // Fallback: fetch directly from server if not found in current space lists
        try {
            isLoadingTaskDetails.value = true;
            isTaskModalOpen.value = true;
            const res = await fetch(`/tasks/${taskIdNum}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.task) {
                    await openTaskModal(data.task);
                    if (preferredTab) {
                        taskModalTab.value = preferredTab;
                    }
                }
            }
        } catch (e) {
            console.error('Error opening task by ID', e);
        } finally {
            isLoadingTaskDetails.value = false;
        }
    }
};

const checkUrlTaskId = () => {
    if (typeof window === 'undefined') return;
    const params = new URLSearchParams(window.location.search);
    const taskId = params.get('task_id');
    const tab = params.get('tab') || 'comments';
    if (taskId) {
        openTaskById(taskId, tab);
    }
};

const closeTaskModal = () => {
    isTaskModalOpen.value = false;
    selectedTask.value = null;
    taskDependencyIds.value = [];
    selectedPrerequisiteTaskId.value = '';
    modalCleanSnapshot.value = null;

    if (typeof window !== 'undefined' && window.location.search) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('task_id')) {
            url.searchParams.delete('task_id');
            url.searchParams.delete('tab');
            window.history.replaceState({}, '', url.pathname + (url.search ? url.search : '') + url.hash);
        }
    }
};

// Sync updated task into localSpace across lists & stages
const syncTaskInLocalSpace = (updatedTask) => {
    if (!localSpace.value?.lists) return;
    for (const l of localSpace.value.lists) {
        for (const s of l.stages || []) {
            const idx = (s.tasks || []).findIndex((t) => t.id === updatedTask.id);
            if (idx > -1) {
                if (s.id !== updatedTask.stage_id) {
                    s.tasks.splice(idx, 1);
                    const targetStage = l.stages.find((stg) => stg.id === updatedTask.stage_id);
                    if (targetStage) {
                        if (!targetStage.tasks) targetStage.tasks = [];
                        targetStage.tasks.push(updatedTask);
                    }
                } else {
                    s.tasks[idx] = { ...s.tasks[idx], ...updatedTask };
                }
            }
        }
    }
};

// Save Task Attributes
const saveTaskDetails = async (showAlert = false) => {
    if (!selectedTask.value || isSavingTask.value) return;
    if (isViewer.value) return;

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
                start_date: taskStartDate.value || null,
                due_at: taskDueAt.value || null,
                estimate_minutes: taskEstimateMinutes.value ? parseInt(taskEstimateMinutes.value) : null,
                assignee_ids: taskAssigneeIds.value,
                dependency_ids: taskDependencyIds.value,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.task) {
                selectedTask.value = data.task;
                taskDependencyIds.value = data.task.dependencies ? data.task.dependencies.map((d) => d.id) : [];
                syncTaskInLocalSpace(data.task);
                captureModalCleanSnapshot(data.task);
            }
            if (showAlert) {
                showToast('Perubahan task berhasil disimpan!', 'success');
            }
        } else {
            if (showAlert) {
                showToast('Gagal menyimpan perubahan task.', 'error');
            }
        }
    } catch (err) {
        console.error('Error saving task', err);
        if (showAlert) {
            showToast('Terjadi kesalahan saat menyimpan perubahan.', 'error');
        }
    } finally {
        isSavingTask.value = false;
    }
};

// Task Dependencies Helpers
const addDependency = async (prereqTaskId) => {
    if (isViewer.value) return;
    const id = parseInt(prereqTaskId);
    if (!id || taskDependencyIds.value.includes(id)) return;

    taskDependencyIds.value.push(id);
    selectedPrerequisiteTaskId.value = '';
    await saveTaskDetails(true);
};

const removeDependency = async (prereqTaskId) => {
    if (isViewer.value) return;
    const id = parseInt(prereqTaskId);
    const idx = taskDependencyIds.value.indexOf(id);
    if (idx > -1) {
        taskDependencyIds.value.splice(idx, 1);
        await saveTaskDetails(true);
    }
};

// Update task dates emitted from GanttTimeline
const handleUpdateTaskDates = async ({ taskId, startDate, dueAt }) => {
    if (isViewer.value) return;

    let updatedTask = null;
    let oldStartDate = null;
    let oldDueAt = null;

    for (const list of localSpace.value.lists || []) {
        for (const stg of list.stages || []) {
            const found = (stg.tasks || []).find((t) => t.id === taskId);
            if (found) {
                updatedTask = found;
                oldStartDate = found.start_date;
                oldDueAt = found.due_at;
                found.start_date = startDate;
                found.due_at = dueAt;
                break;
            }
        }
    }

    try {
        const res = await fetch(`/tasks/${taskId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify({
                title: updatedTask?.title,
                priority: updatedTask?.priority || 'medium',
                stage_id: updatedTask?.stage_id,
                start_date: startDate,
                due_at: dueAt,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            if (data.task) {
                syncTaskInLocalSpace(data.task);
            }
            showToast('Timeline tugas berhasil diperbarui', 'success');
        } else {
            if (updatedTask) {
                updatedTask.start_date = oldStartDate;
                updatedTask.due_at = oldDueAt;
            }
            showToast('Gagal memperbarui timeline tugas.', 'error');
        }
    } catch (e) {
        if (updatedTask) {
            updatedTask.start_date = oldStartDate;
            updatedTask.due_at = oldDueAt;
        }
        showToast('Koneksi terputus saat memperbarui timeline tugas.', 'error');
    }
};

// ==========================================
// SELECT2-STYLE SEARCHABLE ASSIGNEES
// ==========================================
const assigneeSearchQuery = ref('');
const isAssigneeDropdownOpen = ref(false);

const filteredMembers = computed(() => {
    const list = localSpace.value?.members || props.space.members || [];
    if (!assigneeSearchQuery.value.trim()) return list;
    const q = assigneeSearchQuery.value.toLowerCase();
    return list.filter((m) =>
        m.name.toLowerCase().includes(q) ||
        (m.email && m.email.toLowerCase().includes(q))
    );
});

const selectedAssigneeObjects = computed(() => {
    const members = localSpace.value?.members || props.space.members || [];
    return members.filter((m) => taskAssigneeIds.value.includes(m.id));
});

const toggleAssignee = (memberId) => {
    if (isViewer.value) return;
    const idx = taskAssigneeIds.value.indexOf(memberId);
    if (idx > -1) {
        taskAssigneeIds.value.splice(idx, 1);
    } else {
        taskAssigneeIds.value.push(memberId);
    }
    saveTaskDetails(false);
};

const removeAssignee = (memberId) => {
    if (isViewer.value) return;
    taskAssigneeIds.value = taskAssigneeIds.value.filter((id) => id !== memberId);
    saveTaskDetails(false);
};

// ==========================================
// FILE PREVIEW MODAL (PDF & IMAGES)
// ==========================================
const previewingFile = ref(null);
const isPreviewModalOpen = ref(false);

const isImageFile = (att) => {
    if (!att) return false;
    const mime = (att.detected_mime || '').toLowerCase();
    const name = (att.original_name || '').toLowerCase();
    return mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|svg)$/i.test(name);
};

const isPdfFile = (att) => {
    if (!att) return false;
    const mime = (att.detected_mime || '').toLowerCase();
    const name = (att.original_name || '').toLowerCase();
    return mime.includes('pdf') || /\.pdf$/i.test(name);
};

const openFilePreview = (att) => {
    previewingFile.value = att;
    isPreviewModalOpen.value = true;
};

const closeFilePreview = () => {
    isPreviewModalOpen.value = false;
    previewingFile.value = null;
};

// Checklist Handlers
const addChecklist = async () => {
    if (isViewer.value) return;
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
            showToast('Item checklist berhasil ditambahkan!', 'success');
        }
    } catch (err) {
        console.error('Error adding checklist', err);
        showToast('Gagal menambahkan item checklist', 'error');
    } finally {
        isSubmittingChecklist.value = false;
    }
};

const toggleChecklist = async (chk) => {
    if (isViewer.value || !selectedTask.value) return;

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
        }
    } catch (err) {
        console.error('Error toggling checklist', err);
    }
};

const deleteChecklist = async (chk) => {
    if (isViewer.value || !selectedTask.value) return;

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
            showToast('Item checklist berhasil dihapus', 'success');
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
    if (isViewer.value) return;
    if (fileInputRef.value) {
        fileInputRef.value.click();
    }
};

const handleFileUpload = async (event) => {
    if (isViewer.value) return;
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
            if (data.attachment) {
                const attId = String(data.attachment.id);
                const exists = selectedTask.value.attachments.some((a) => String(a.id) === attId);
                if (!exists) {
                    selectedTask.value.attachments.unshift(data.attachment);
                }
            }
            if (fileInputRef.value) fileInputRef.value.value = '';
            showToast('Berkas lampiran berhasil diunggah!', 'success');
        }
    } catch (err) {
        console.error('Error uploading attachment', err);
        showToast('Gagal mengunggah berkas lampiran', 'error');
    } finally {
        isUploadingAttachment.value = false;
    }
};

const downloadAttachment = (att) => {
    if (!selectedTask.value) return;
    window.location.href = `/tasks/${selectedTask.value.id}/attachments/${att.id}/download`;
};

const deleteAttachment = async (att) => {
    if (isViewer.value) return;
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
            showToast('Berkas lampiran berhasil dihapus', 'success');
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
            if (data.comment) {
                const commentId = String(data.comment.id);
                const exists = selectedTask.value.comments.some((c) => String(c.id) === commentId);
                if (!exists) {
                    selectedTask.value.comments.unshift(data.comment);
                }
            }
            newCommentContent.value = '';
            showToast('Komentar berhasil dikirim!', 'success');
        }
    } catch (err) {
        console.error('Error adding comment', err);
        showToast('Gagal mengirim komentar', 'error');
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
            showToast('Komentar berhasil dihapus', 'success');
        }
    } catch (err) {
        console.error('Error deleting comment', err);
    }
};

// ==========================================
// REAL-TIME WEBSOCKET PUSH (LARAVEL ECHO & REVERB)
// ==========================================
const isDraggingCard = ref(false);
const isMovingCard = ref(false);

// Realtime Connection & Status
const connectionState = ref('connected');
const isConnected = computed(() => connectionState.value === 'connected');
const isReconnecting = computed(() => connectionState.value === 'connecting' || connectionState.value === 'reconnecting');
const isOffline = computed(() => ['disconnected', 'unavailable', 'failed'].includes(connectionState.value));

const reconnectEcho = () => {
    connectionState.value = 'connecting';
    const echoInstance = window.Echo || echo;
    if (echoInstance?.connector?.pusher) {
        try {
            echoInstance.connector.pusher.disconnect();
        } catch (e) {}
        setTimeout(() => {
            try {
                echoInstance.connector.pusher.connect();
            } catch (e) {}
        }, 200);
    }
};

// One-time callable syncSpaceData (on mount or when reconnecting after connection drop)
const syncSpaceData = async () => {
    // Avoid syncing while user is dragging a card, moving a card, or actively typing a new quick task
    if (isDraggingCard.value || isMovingCard.value || isSubmittingTask.value || activeQuickAddStageId.value) return;

    try {
        const res = await fetch(`/spaces/${props.space.slug}/sync`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (res.ok) {
            const data = await res.json();
            if (data.space?.lists) {
                const newJson = JSON.stringify(data.space.lists);
                const oldJson = JSON.stringify(localSpace.value.lists);
                if (newJson !== oldJson) {
                    localSpace.value.lists = data.space.lists;

                    // If task modal is open, sync comments, checklists, attachments, and dependencies
                    if (selectedTask.value) {
                        for (const list of data.space.lists) {
                            for (const stage of list.stages || []) {
                                const found = (stage.tasks || []).find((t) => t.id === selectedTask.value.id);
                                if (found) {
                                    if (JSON.stringify(found.comments) !== JSON.stringify(selectedTask.value.comments)) {
                                        selectedTask.value.comments = found.comments || [];
                                    }
                                    if (JSON.stringify(found.checklists) !== JSON.stringify(selectedTask.value.checklists)) {
                                        selectedTask.value.checklists = found.checklists || [];
                                    }
                                    if (JSON.stringify(found.attachments) !== JSON.stringify(selectedTask.value.attachments)) {
                                        selectedTask.value.attachments = found.attachments || [];
                                    }
                                    if (JSON.stringify(found.dependencies) !== JSON.stringify(selectedTask.value.dependencies)) {
                                        selectedTask.value.dependencies = found.dependencies || [];
                                        taskDependencyIds.value = (found.dependencies || []).map((d) => d.id);
                                    }
                                    if (JSON.stringify(found.dependents) !== JSON.stringify(selectedTask.value.dependents)) {
                                        selectedTask.value.dependents = found.dependents || [];
                                    }
                                }
                            }
                        }
                    }

                    nextTick(() => {
                        initSortable();
                    });
                }
            }
        }
    } catch (e) {
        // silent catch on network glitches
    }
};

// Realtime Event Handlers
const handleTaskCreated = (event) => {
    const task = event.task || event;
    if (!task || !task.id) return;

    // Check if task already exists anywhere in localSpace
    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            if ((stage.tasks || []).some((t) => t.id === task.id)) {
                return;
            }
        }
    }

    let inserted = false;
    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            if (stage.id === task.stage_id) {
                if (!stage.tasks) stage.tasks = [];
                if (!task.stage) {
                    task.stage = {
                        id: stage.id,
                        name: stage.name,
                        color: stage.color,
                        is_done: stage.is_done,
                    };
                }
                stage.tasks.push(task);
                stage.tasks.sort((a, b) => (a.position || 0) - (b.position || 0));
                inserted = true;
                break;
            }
        }
        if (inserted) break;
    }

    if (!inserted && currentList.value?.stages?.[0]) {
        if (!currentList.value.stages[0].tasks) currentList.value.stages[0].tasks = [];
        currentList.value.stages[0].tasks.push(task);
    }

    nextTick(() => {
        initSortable();
    });
};

const handleTaskUpdated = (event) => {
    const updatedTask = event.task || event;
    if (!updatedTask || !updatedTask.id) return;

    syncTaskInLocalSpace(updatedTask);

    if (isTaskModalOpen.value && selectedTask.value && selectedTask.value.id === updatedTask.id) {
        if (hasUncommittedEdits()) {
            showToast(
                'Perhatian: Task ini baru saja diperbarui oleh pengguna lain. Harap tinjau kembali perubahan Anda.',
                'warning'
            );
        } else {
            selectedTask.value = updatedTask;
            taskTitle.value = updatedTask.title || '';
            taskDescription.value = updatedTask.description || '';
            taskPriority.value = updatedTask.priority || 'medium';
            taskStageId.value = updatedTask.stage_id;
            taskStartDate.value = updatedTask.start_date ? String(updatedTask.start_date).substring(0, 10) : '';
            taskDueAt.value = updatedTask.due_at ? String(updatedTask.due_at).substring(0, 10) : '';
            taskEstimateMinutes.value = updatedTask.estimate_minutes || '';
            taskAssigneeIds.value = updatedTask.assignees ? updatedTask.assignees.map((a) => a.id) : [];
            taskDependencyIds.value = updatedTask.dependencies ? updatedTask.dependencies.map((d) => d.id) : [];
            captureModalCleanSnapshot(updatedTask);
        }
    }

    nextTick(() => {
        initSortable();
    });
};

const handleTaskMoved = (event) => {
    const taskId = event.task_id || event.task?.id || event.id;
    const targetStageId = event.to_stage_id || event.stage_id || event.new_stage_id || event.task?.stage_id;
    const newPosition = event.position !== undefined ? parseInt(event.position) : (event.task?.position ?? 1);
    const movedByUserId = event.user_id || event.moved_by;

    if (!taskId) return;

    if (movedByUserId && currentUser.value && movedByUserId === currentUser.value.id && isMovingCard.value) {
        return;
    }

    const taskIdNum = parseInt(taskId);
    const targetStageIdNum = parseInt(targetStageId);

    let movedTask = null;
    let targetStage = null;

    for (const list of localSpace.value.lists || []) {
        for (const stg of list.stages || []) {
            if (stg.id === targetStageIdNum) {
                targetStage = stg;
            }
            const idx = (stg.tasks || []).findIndex((t) => t.id === taskIdNum);
            if (idx > -1) {
                const [removed] = stg.tasks.splice(idx, 1);
                movedTask = removed;
                stg.tasks.forEach((t, i) => {
                    t.position = i + 1;
                });
            }
        }
    }

    if (!movedTask && event.task) {
        movedTask = JSON.parse(JSON.stringify(event.task));
    }

    if (movedTask && targetStage) {
        movedTask.stage_id = targetStageIdNum;
        movedTask.stage = {
            id: targetStage.id,
            name: targetStage.name,
            color: targetStage.color,
            is_done: targetStage.is_done,
        };

        if (!targetStage.tasks) targetStage.tasks = [];
        const insertIdx = Math.max(0, Math.min(newPosition - 1, targetStage.tasks.length));
        targetStage.tasks.splice(insertIdx, 0, movedTask);

        targetStage.tasks.forEach((t, i) => {
            t.position = i + 1;
        });

        if (selectedTask.value && selectedTask.value.id === taskIdNum) {
            selectedTask.value.stage_id = targetStageIdNum;
            taskStageId.value = targetStageIdNum;
            if (modalCleanSnapshot.value) {
                modalCleanSnapshot.value.stage_id = targetStageIdNum;
            }

            // Append realtime audit log entry in task modal
            if (event.user && targetStage) {
                if (!taskActivityLogs.value) taskActivityLogs.value = [];
                taskActivityLogs.value.unshift({
                    id: 'act_' + Date.now(),
                    action: 'task_moved',
                    actor: {
                        id: event.user.id,
                        name: event.user.name,
                        email: event.user.email,
                    },
                    created_at: new Date().toISOString(),
                    metadata: {
                        title: movedTask.title,
                        new_stage: targetStage.name,
                        is_done: targetStage.is_done,
                    },
                });
            }
        }
    } else {
        syncSpaceData();
    }

    nextTick(() => {
        initSortable();
    });
};

const handleTaskDeleted = (event) => {
    const taskId = event.task_id || event.task?.id || event.id;
    if (!taskId) return;
    const taskIdNum = parseInt(taskId);

    if (isTaskModalOpen.value && selectedTask.value && selectedTask.value.id === taskIdNum) {
        closeTaskModal();
        showToast('Task ini telah dihapus oleh pengguna lain.', 'info');
    }

    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            const idx = (stage.tasks || []).findIndex((t) => t.id === taskIdNum);
            if (idx > -1) {
                stage.tasks.splice(idx, 1);
                stage.tasks.forEach((t, i) => {
                    t.position = i + 1;
                });
            }
        }
    }

    nextTick(() => {
        initSortable();
    });
};

const handleTaskChecklistToggled = (event) => {
    const taskId = event.taskId || event.task_id || event.checklist?.task_id || event.task?.id;
    const checklistId = event.checklistId || event.checklist_id || event.checklist?.id || event.id;
    const isCompleted = event.isCompleted !== undefined ? !!event.isCompleted : (event.is_completed !== undefined ? !!event.is_completed : false);

    if (!taskId || checklistId === undefined) return;
    const taskIdNum = parseInt(taskId);
    const checklistIdNum = parseInt(checklistId);

    if (selectedTask.value && selectedTask.value.id === taskIdNum) {
        if (selectedTask.value.checklists) {
            const chk = selectedTask.value.checklists.find((c) => c.id === checklistIdNum);
            if (chk) {
                chk.is_completed = isCompleted;
            }
        }
        if (event.checklistsCount !== undefined) {
            selectedTask.value.checklists_count = event.checklistsCount;
        }
        if (event.completedChecklistsCount !== undefined) {
            selectedTask.value.completed_checklists_count = event.completedChecklistsCount;
        }
    }

    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            const foundTask = (stage.tasks || []).find((t) => t.id === taskIdNum);
            if (foundTask) {
                if (foundTask.checklists) {
                    const chk = foundTask.checklists.find((c) => c.id === checklistIdNum);
                    if (chk) {
                        chk.is_completed = isCompleted;
                    }
                }
                if (event.checklistsCount !== undefined) {
                    foundTask.checklists_count = event.checklistsCount;
                }
                if (event.completedChecklistsCount !== undefined) {
                    foundTask.completed_checklists_count = event.completedChecklistsCount;
                }
            }
        }
    }
};

const handleTaskCommentAdded = (event) => {
    const comment = event.comment || event;
    const taskId = event.taskId || event.task_id || comment.task_id;
    if (!comment || !taskId) return;
    const taskIdNum = parseInt(taskId);
    const commentId = String(comment.id);

    if (selectedTask.value && selectedTask.value.id === taskIdNum) {
        if (!selectedTask.value.comments) {
            selectedTask.value.comments = [];
        }
        const exists = selectedTask.value.comments.some((c) => String(c.id) === commentId);
        if (!exists) {
            selectedTask.value.comments.unshift(comment);
        }
    }

    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            const foundTask = (stage.tasks || []).find((t) => t.id === taskIdNum);
            if (foundTask) {
                if (!foundTask.comments) foundTask.comments = [];
                const exists = foundTask.comments.some((c) => String(c.id) === commentId);
                if (!exists) {
                    foundTask.comments.unshift(comment);
                }
                foundTask.comments_count = foundTask.comments.length;
            }
        }
    }
};

const handleTaskAttachmentUploaded = (event) => {
    const attachment = event.attachment || event;
    const taskId = event.taskId || event.task_id || attachment.task_id;
    if (!attachment || !taskId) return;
    const taskIdNum = parseInt(taskId);
    const attachmentId = String(attachment.id);

    if (selectedTask.value && selectedTask.value.id === taskIdNum) {
        if (!selectedTask.value.attachments) {
            selectedTask.value.attachments = [];
        }
        const exists = selectedTask.value.attachments.some((a) => String(a.id) === attachmentId);
        if (!exists) {
            selectedTask.value.attachments.unshift(attachment);
        }
    }

    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            const foundTask = (stage.tasks || []).find((t) => t.id === taskIdNum);
            if (foundTask) {
                if (!foundTask.attachments) foundTask.attachments = [];
                const exists = foundTask.attachments.some((a) => String(a.id) === attachmentId);
                if (!exists) {
                    foundTask.attachments.unshift(attachment);
                }
            }
        }
    }
};

const handleTaskAttachmentDeleted = (event) => {
    const taskId = event.taskId || event.task_id || event.attachment?.task_id;
    const attachmentId = event.attachmentId || event.attachment_id || event.attachment?.id || event.id;
    if (!taskId || !attachmentId) return;
    const taskIdNum = parseInt(taskId);
    const attachmentIdNum = parseInt(attachmentId);

    if (selectedTask.value && selectedTask.value.id === taskIdNum) {
        if (selectedTask.value.attachments) {
            selectedTask.value.attachments = selectedTask.value.attachments.filter((a) => a.id !== attachmentIdNum);
        }
    }

    for (const list of localSpace.value.lists || []) {
        for (const stage of list.stages || []) {
            const foundTask = (stage.tasks || []).find((t) => t.id === taskIdNum);
            if (foundTask && foundTask.attachments) {
                foundTask.attachments = foundTask.attachments.filter((a) => a.id !== attachmentIdNum);
            }
        }
    }
};

const handleSpaceStageChanged = (event) => {
    const action = event.action;
    const stage = event.stage;
    const listId = event.listId || event.list_id;

    if (action && stage) {
        for (const list of localSpace.value.lists || []) {
            if (!listId || list.id === listId) {
                if (!list.stages) list.stages = [];
                if (action === 'created') {
                    if (!list.stages.some((s) => s.id === stage.id)) {
                        list.stages.push(stage);
                    }
                } else if (action === 'updated') {
                    const idx = list.stages.findIndex((s) => s.id === stage.id);
                    if (idx > -1) {
                        list.stages[idx] = { ...list.stages[idx], ...stage };
                    }
                } else if (action === 'deleted') {
                    list.stages = list.stages.filter((s) => s.id !== stage.id);
                }
            }
        }
    } else if (event.stages && Array.isArray(event.stages)) {
        if (currentList.value) {
            currentList.value.stages = event.stages;
        }
        for (const list of localSpace.value.lists || []) {
            if (event.listId ? list.id === event.listId : (event.list_id ? list.id === event.list_id : true)) {
                list.stages = event.stages;
            }
        }
    } else {
        syncSpaceData();
    }
    nextTick(() => {
        initSortable();
    });
};

// Echo Channel Subscription
let echoChannel = null;

const initEcho = () => {
    const echoInstance = window.Echo || echo;
    if (!echoInstance) return;

    const conn = echoInstance.connector?.pusher?.connection;
    if (conn) {
        connectionState.value = conn.state || 'connected';

        conn.bind('state_change', (states) => {
            const prev = states.previous;
            const current = states.current;
            connectionState.value = current;

            // When connection transitions back to connected, trigger a single syncSpaceData()
            if (current === 'connected' && prev && prev !== 'connected') {
                syncSpaceData();
            }
        });
        conn.bind('connected', () => {
            connectionState.value = 'connected';
        });
        conn.bind('connecting', () => {
            connectionState.value = 'connecting';
        });
        conn.bind('disconnected', () => {
            connectionState.value = 'disconnected';
        });
        conn.bind('unavailable', () => {
            connectionState.value = 'unavailable';
        });
        conn.bind('failed', () => {
            connectionState.value = 'failed';
        });
    }

    const channelName = `space.${props.space.id}`;
    echoChannel = echoInstance.private(channelName);

    echoChannel
        .listen('TaskCreatedEvent', handleTaskCreated)
        .listen('TaskUpdatedEvent', handleTaskUpdated)
        .listen('TaskMovedEvent', handleTaskMoved)
        .listen('TaskDeletedEvent', handleTaskDeleted)
        .listen('TaskChecklistToggledEvent', handleTaskChecklistToggled)
        .listen('TaskCommentAddedEvent', handleTaskCommentAdded)
        .listen('TaskAttachmentUploadedEvent', handleTaskAttachmentUploaded)
        .listen('TaskAttachmentDeletedEvent', handleTaskAttachmentDeleted)
        .listen('SpaceStageChangedEvent', handleSpaceStageChanged);
};

const cleanupEcho = () => {
    const echoInstance = window.Echo || echo;
    if (echoInstance) {
        echoInstance.leave(`space.${props.space.id}`);
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
            disabled: isViewer.value,
            animation: 200,
            ghostClass: 'kanban-ghost-card',
            chosenClass: 'kanban-chosen-card',
            dragClass: 'kanban-drag-card',
            fallbackTolerance: 3,
            onStart: () => {
                isDraggingCard.value = true;
            },
            onEnd: async (evt) => {
                isDraggingCard.value = false;
                const taskId = evt.item.dataset.taskId;
                const fromStageId = evt.from.dataset.stageId;
                const newStageId = evt.to.dataset.stageId;
                const newPosition = evt.newIndex + 1;

                if (!taskId || !newStageId || !fromStageId) return;
                if (fromStageId === newStageId && evt.oldIndex === evt.newIndex) return;

                const taskIdNum = parseInt(taskId);
                const sourceStageIdNum = parseInt(fromStageId);
                const targetStageIdNum = parseInt(newStageId);

                // Snapshot backup for revert on failure
                const backupLists = JSON.parse(JSON.stringify(localSpace.value.lists || []));

                // Optimistic state update in localSpace.value immediately
                let sourceStage = null;
                let targetStage = null;
                for (const list of localSpace.value.lists || []) {
                    for (const stg of list.stages || []) {
                        if (stg.id === sourceStageIdNum) sourceStage = stg;
                        if (stg.id === targetStageIdNum) targetStage = stg;
                    }
                }

                if (sourceStage && targetStage) {
                    const taskIdx = (sourceStage.tasks || []).findIndex((t) => t.id === taskIdNum);
                    if (taskIdx > -1) {
                        const [movedTask] = sourceStage.tasks.splice(taskIdx, 1);
                        movedTask.stage_id = targetStageIdNum;
                        if (sourceStage.id !== targetStage.id) {
                            movedTask.stage = {
                                id: targetStage.id,
                                name: targetStage.name,
                                color: targetStage.color,
                                is_done: targetStage.is_done,
                            };
                        }
                        if (!targetStage.tasks) targetStage.tasks = [];
                        targetStage.tasks.splice(evt.newIndex, 0, movedTask);

                        // Recalculate/normalize positions
                        sourceStage.tasks.forEach((t, idx) => {
                            t.position = idx + 1;
                        });
                        if (sourceStage.id !== targetStage.id) {
                            targetStage.tasks.forEach((t, idx) => {
                                t.position = idx + 1;
                            });
                        }
                    }
                }

                isMovingCard.value = true;
                try {
                    const response = await fetch(`/tasks/${taskId}/move`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                            Accept: 'application/json',
                        },
                        body: JSON.stringify({
                            stage_id: targetStageIdNum,
                            position: newPosition,
                        }),
                    });

                    if (!response.ok) {
                        localSpace.value.lists = backupLists;
                        showToast('Gagal memindahkan task. Perubahan dibatalkan.', 'error');
                        nextTick(() => {
                            initSortable();
                        });
                    }
                } catch (err) {
                    console.error('Koneksi terputus saat memindahkan task', err);
                    localSpace.value.lists = backupLists;
                    showToast('Koneksi terputus saat memindahkan task. Perubahan dibatalkan.', 'error');
                    nextTick(() => {
                        initSortable();
                    });
                } finally {
                    isMovingCard.value = false;
                }
            },
        });
        sortableInstances.push(sortable);
    });
};

onMounted(() => {
    nextTick(() => {
        initSortable();
        checkUrlTaskId();
    });
    initEcho();
});

watch(
    () => page.url,
    () => {
        nextTick(() => {
            checkUrlTaskId();
        });
    }
);

onUnmounted(() => {
    cleanupEcho();
});

watch(
    () => localSpace.value,
    () => {
        if (!isDraggingCard.value && !isMovingCard.value) {
            nextTick(() => {
                initSortable();
            });
        }
    },
    { deep: true }
);

watch(selectedListId, () => {
    nextTick(() => {
        initSortable();
    });
});

watch(isViewer, () => {
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
        (stg.tasks || []).map((t) => ({
            ...t,
            stage_name: stg.name,
            stage_color: stg.color,
            stage: stg,
        }))
    );
});

const availablePrerequisiteTasks = computed(() => {
    if (!selectedTask.value) return [];
    return allCurrentTasks.value.filter((t) => {
        if (t.id === selectedTask.value.id) return false;
        if (taskDependencyIds.value.includes(t.id)) return false;
        const isDependent = (selectedTask.value.dependents || []).some((dep) => dep.id === t.id);
        if (isDependent) return false;
        return true;
    });
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
                        <h4 class="fw-bold mb-1 d-flex align-items-center flex-wrap gap-2">
                            <span>{{ space.name }}</span>
                            <span
                                class="badge rounded-pill px-2 py-1 fs-11"
                                :class="space.status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary'"
                            >
                                {{ space.status === 'active' ? 'Aktif' : 'Selesai' }}
                            </span>
                            <!-- Realtime WebSocket Push Status Indicator -->
                            <span
                                v-if="isConnected"
                                class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fs-11 d-inline-flex align-items-center gap-1.5 shadow-2xs"
                                title="WebSocket Reverb aktif - kolaborasi realtime terhubung"
                            >
                                <span class="spinner-grow spinner-grow-sm text-success" style="width: 7px; height: 7px;" role="status"></span>
                                <span>Realtime Terhubung</span>
                            </span>

                            <span
                                v-else-if="isReconnecting"
                                class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 fs-11 d-inline-flex align-items-center gap-1.5 shadow-2xs"
                                title="Sedang menghubungkan kembali ke server WebSocket..."
                            >
                                <span class="spinner-border spinner-border-sm text-warning" style="width: 8px; height: 8px; border-width: 1.5px;" role="status"></span>
                                <span>Menghubungkan kembali...</span>
                            </span>

                            <div v-else class="d-inline-flex align-items-center gap-1.5">
                                <span
                                    class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fs-11 d-inline-flex align-items-center gap-1.5 shadow-2xs"
                                    title="Koneksi WebSocket terputus"
                                >
                                    <span class="rounded-circle bg-danger" style="width: 7px; height: 7px;"></span>
                                    <span>Offline</span>
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline-danger rounded-pill py-0.5 px-2 fs-11 fw-semibold d-inline-flex align-items-center gap-1 shadow-2xs"
                                    @click="reconnectEcho"
                                    title="Sambungkan ulang WebSocket sekarang"
                                >
                                    <i class="ri-refresh-line"></i>
                                    <span>Sambungkan Ulang</span>
                                </button>
                            </div>
                        </h4>
                        <p class="text-muted fs-13 mb-0" style="max-width: 650px;">
                            {{ space.description || 'Ruang kerja kolaboratif tim.' }}
                        </p>
                    </div>

                    <!-- Right Meta: Members & Deadline -->
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div v-if="space.due_at" class="bg-light border rounded-pill px-3 py-1 fs-12 text-muted">
                            <i class="ri-calendar-line me-1 text-primary"></i> Tenggat: <span class="fw-semibold text-body">{{ formatDate(space.due_at) }}</span>
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
                <ul class="nav nav-tabs border-bottom-0 mt-4 mb-0 flex-nowrap overflow-x-auto text-nowrap pb-1">
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
                            :class="{ 'active border-bottom border-primary border-3 text-primary': activeTab === 'timeline' }"
                            @click="activeTab = 'timeline'"
                        >
                            <i class="ri-git-commit-line me-1"></i> Timeline (Gantt)
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
                    <li v-if="!isViewer" class="nav-item">
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
                <template v-for="stage in currentList?.stages" :key="stage.id">
                    <div
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
                        <div v-if="!isViewer" class="d-flex align-items-center gap-1">
                            <button
                                type="button"
                                class="btn btn-sm btn-light p-1 rounded-circle text-muted"
                                :title="`Tambah tahapan status baru setelah '${stage.name}'`"
                                @click="openAddStageForm(stage.id)"
                            >
                                <i class="ri-add-line fs-14"></i>
                            </button>
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light p-1 rounded-circle text-muted"
                                    data-bs-toggle="dropdown"
                                    title="Opsi Tahapan"
                                >
                                    <i class="ri-more-line fs-13"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow border rounded-3 p-1 fs-12">
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item py-1 px-2 d-flex align-items-center gap-2"
                                            @click="openAddStageForm(stage.id)"
                                        >
                                            <i class="ri-add-line text-primary"></i> Tambah Tahapan Setelah Ini
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <button
                                            type="button"
                                            class="dropdown-item py-1 px-2 text-danger d-flex align-items-center gap-2"
                                            @click="deleteStage(stage)"
                                        >
                                            <i class="ri-delete-bin-line"></i> Hapus Tahapan
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
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
                            <select
                                v-model="quickTaskPriority"
                                class="form-select form-select-sm border-0 py-1 fs-11 text-muted shadow-none bg-light rounded-2"
                                style="padding-left: 8px; padding-right: 28px !important; min-width: 105px; cursor: pointer;"
                            >
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
                                <div class="d-flex align-items-center gap-1">
                                    <span class="badge rounded-pill px-2 py-1 fs-10 text-uppercase" :class="priorityColor(task.priority)">
                                        {{ task.priority }}
                                    </span>
                                    <!-- Dependency Link Badge -->
                                    <span
                                        v-if="task.dependencies?.length"
                                        class="badge bg-secondary-subtle text-secondary rounded-pill px-1-5 py-0 fs-10 d-inline-flex align-items-center gap-1"
                                        :title="`Bergantung pada ${task.dependencies.length} tugas lain`"
                                    >
                                        <i class="ri-links-line"></i>
                                        <span>{{ task.dependencies.length }}</span>
                                    </span>
                                </div>
                                <div v-if="!isViewer" class="dropdown" @click.stop>
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
                                        <li v-if="!isViewer"><hr class="dropdown-divider my-1"></li>
                                        <li v-if="!isViewer">
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
                                    <span
                                        v-if="task.due_at"
                                        class="d-flex align-items-center gap-1"
                                        :class="isOverdueDate(task.due_at, stage.is_done) ? 'text-danger fw-semibold' : 'text-muted'"
                                        :title="'Tenggat: ' + formatDate(task.due_at)"
                                    >
                                        <i class="ri-calendar-line"></i> {{ formatDueDate(task.due_at) }}
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
                            v-if="!isViewer && activeQuickAddStageId !== stage.id"
                            type="button"
                            class="btn btn-sm btn-light border-dashed w-100 text-muted fs-12 mt-1 py-1"
                            @click="openQuickAdd(stage.id)"
                        >
                            <i class="ri-add-line me-1"></i> Tambah Task
                        </button>
                    </div>
                </div>

                <!-- Inline Add Stage Form Column: appears immediately after this stage -->
                    <div
                        v-if="isAddingStage && insertAfterStageId === stage.id"
                        class="card border border-primary shadow-sm rounded-3 p-3 bg-body"
                        style="width: 295px; min-width: 295px; height: fit-content;"
                    >
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold fs-13 mb-0 d-flex align-items-center gap-1 text-primary">
                                <i class="ri-kanban-view"></i>
                                <span>Setelah '{{ stage.name }}'</span>
                            </h6>
                            <button type="button" class="btn-close fs-10" @click="cancelAddStage"></button>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs-11 text-muted fw-semibold mb-1">Nama Tahapan Baru</label>
                            <input
                                v-model="newStageName"
                                type="text"
                                class="form-control form-control-sm rounded-2 fs-12"
                                placeholder="Misal: In Testing, QA, Staging..."
                                @keydown.enter.prevent="submitCreateStage"
                                autofocus
                            />
                        </div>

                        <!-- Pilih Warna Preset -->
                        <div class="mb-2">
                            <label class="form-label fs-11 text-muted fw-semibold mb-1">Warna Kolom</label>
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span
                                    v-for="c in stageColorPresets"
                                    :key="c"
                                    class="rounded-circle cursor-pointer border border-2"
                                    :style="{ backgroundColor: c, width: '20px', height: '20px', borderColor: newStageColor === c ? '#000' : 'transparent' }"
                                    @click="newStageColor = c"
                                ></span>
                                <input
                                    type="color"
                                    v-model="newStageColor"
                                    class="form-control form-control-color p-0 border-0 rounded-circle"
                                    style="width: 20px; height: 20px; cursor: pointer;"
                                    title="Pilih warna kustom"
                                />
                            </div>
                        </div>

                        <!-- Is Done Toggle -->
                        <div class="form-check form-switch mb-3">
                            <input
                                :id="'stageIsDoneAfter_' + stage.id"
                                v-model="newStageIsDone"
                                class="form-check-input cursor-pointer"
                                type="checkbox"
                            />
                            <label class="form-check-label fs-11 text-muted cursor-pointer" :for="'stageIsDoneAfter_' + stage.id">
                                Anggap sebagai status 'Selesai' (Done)
                            </label>
                        </div>

                        <div class="d-flex justify-content-end gap-1">
                            <button
                                type="button"
                                class="btn btn-sm btn-light py-1 px-3 fs-11 rounded-pill"
                                @click="cancelAddStage"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-primary py-1 px-3 fs-11 fw-semibold rounded-pill"
                                :disabled="!newStageName.trim() || isSubmittingStage"
                                @click="submitCreateStage"
                            >
                                <span v-if="isSubmittingStage" class="spinner-border spinner-border-sm me-1"></span>
                                Simpan Tahapan
                            </button>
                        </div>
                    </div>
                </template>

                <!-- ADD NEW STAGE / STATUS COLUMN AT THE END -->
                <div v-if="!isViewer" style="width: 295px; min-width: 295px;">
                    <!-- Add Column Trigger Button -->
                    <div
                        v-if="!isAddingStage || insertAfterStageId !== null"
                        class="card border border-dashed border-secondary-subtle rounded-3 p-3 text-center cursor-pointer bg-light-subtle hover-shadow transition-all"
                        style="height: fit-content;"
                        @click="openAddStageForm(null)"
                    >
                        <div class="d-flex align-items-center justify-content-center gap-2 text-primary fw-semibold fs-13 py-1">
                            <i class="ri-add-circle-line fs-16"></i>
                            <span>Tambah Tahapan di Akhir</span>
                        </div>
                    </div>

                    <!-- Add Column Inline Form at end -->
                    <div
                        v-else-if="isAddingStage && insertAfterStageId === null"
                        class="card border border-primary shadow-sm rounded-3 p-3 bg-body"
                        style="height: fit-content;"
                    >
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold fs-13 mb-0 d-flex align-items-center gap-1 text-primary">
                                <i class="ri-kanban-view"></i> Tahapan Status Baru (Di Akhir)
                            </h6>
                            <button type="button" class="btn-close fs-10" @click="cancelAddStage"></button>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fs-11 text-muted fw-semibold mb-1">Nama Tahapan</label>
                            <input
                                v-model="newStageName"
                                type="text"
                                class="form-control form-control-sm rounded-2 fs-12"
                                placeholder="Misal: In Testing, QA, Staging..."
                                @keydown.enter.prevent="submitCreateStage"
                                autofocus
                            />
                        </div>

                        <!-- Pilih Warna Preset -->
                        <div class="mb-2">
                            <label class="form-label fs-11 text-muted fw-semibold mb-1">Warna Kolom</label>
                            <div class="d-flex flex-wrap gap-1 align-items-center">
                                <span
                                    v-for="c in stageColorPresets"
                                    :key="c"
                                    class="rounded-circle cursor-pointer border border-2"
                                    :style="{ backgroundColor: c, width: '20px', height: '20px', borderColor: newStageColor === c ? '#000' : 'transparent' }"
                                    @click="newStageColor = c"
                                ></span>
                                <input
                                    type="color"
                                    v-model="newStageColor"
                                    class="form-control form-control-color p-0 border-0 rounded-circle"
                                    style="width: 20px; height: 20px; cursor: pointer;"
                                    title="Pilih warna kustom"
                                />
                            </div>
                        </div>

                        <!-- Is Done Toggle -->
                        <div class="form-check form-switch mb-3">
                            <input
                                id="stageIsDoneEnd"
                                v-model="newStageIsDone"
                                class="form-check-input cursor-pointer"
                                type="checkbox"
                            />
                            <label class="form-check-label fs-11 text-muted cursor-pointer" for="stageIsDoneEnd">
                                Anggap sebagai status 'Selesai' (Done)
                            </label>
                        </div>

                        <div class="d-flex justify-content-end gap-1">
                            <button
                                type="button"
                                class="btn btn-sm btn-light py-1 px-3 fs-11 rounded-pill"
                                @click="cancelAddStage"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm btn-primary py-1 px-3 fs-11 fw-semibold rounded-pill"
                                :disabled="!newStageName.trim() || isSubmittingStage"
                                @click="submitCreateStage"
                            >
                                <span v-if="isSubmittingStage" class="spinner-border spinner-border-sm me-1"></span>
                                Simpan Tahapan
                            </button>
                        </div>
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
                        v-if="!isViewer"
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
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-semibold fs-13 text-body">{{ task.title }}</span>
                                    <span
                                        v-if="task.dependencies?.length"
                                        class="badge bg-secondary-subtle text-secondary rounded-pill px-1-5 py-0 fs-10"
                                        :title="`Bergantung pada ${task.dependencies.length} tugas lain`"
                                    >
                                        <i class="ri-links-line me-1"></i>{{ task.dependencies.length }}
                                    </span>
                                </div>
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
                                <span v-if="task.due_at" class="fs-12" :class="isOverdueDate(task.due_at) ? 'text-danger fw-semibold' : 'text-muted'">
                                    <i class="ri-calendar-line me-1"></i> {{ formatDate(task.due_at) }}
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
                                        <li v-if="!isViewer"><hr class="dropdown-divider my-1"></li>
                                        <li v-if="!isViewer">
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

        <!-- TAB: TIMELINE (GANTT CHART VIEW) -->
        <div v-else-if="activeTab === 'timeline'" class="mb-4">
            <GanttTimeline
                :tasks="allCurrentTasks"
                :stages="currentList?.stages || []"
                :read-only="isViewer"
                @open-task="openTaskModal"
                @update-task-dates="handleUpdateTaskDates"
            />
        </div>

        <!-- TAB 2: OVERVIEW & MEMBERS -->
        <div v-else-if="activeTab === 'overview'" class="card border border-secondary-subtle rounded-3 p-4 shadow-sm">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h5 class="fw-bold fs-16 mb-0">Anggota Tim Space ({{ localSpace.members?.length || 0 }})</h5>
                    <p class="text-muted fs-12 mb-0">Hanya anggota yang terdaftar di bawah ini yang dapat melihat Space ini.</p>
                </div>
            </div>

            <!-- Form Tambah Anggota ke Space -->
            <div v-if="!isViewer" class="card border border-secondary-subtle rounded-3 p-3 mb-4 bg-light-subtle">
                <h6 class="fw-bold fs-13 mb-2 d-flex align-items-center gap-1">
                    <i class="ri-user-add-line text-primary"></i> Tambahkan Anggota Workspace ke Space Ini
                </h6>

                <div v-if="availableWorkspaceUsers.length > 0">
                    <form @submit.prevent="submitAddSpaceMember" class="row g-2 align-items-end">
                        <!-- Select2 User Picker -->
                        <div class="col-md-7 position-relative">
                            <label class="form-label fs-12 fw-semibold mb-1">Pilih Anggota</label>
                            
                            <div
                                class="form-control form-control-sm p-1 d-flex flex-wrap align-items-center gap-1 cursor-pointer bg-light-subtle rounded-2 min-h-38"
                                :class="{ 'border-primary shadow-xs': isSpaceUserDropdownOpen }"
                                @click="isSpaceUserDropdownOpen = !isSpaceUserDropdownOpen"
                            >
                                <span
                                    v-for="u in selectedSpaceUserObjects"
                                    :key="u.id"
                                    class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill py-1 px-2 d-flex align-items-center gap-1 fs-11"
                                    @click.stop
                                >
                                    {{ u.name }}
                                    <i class="ri-close-line cursor-pointer" @click="toggleSelectSpaceUser(u)"></i>
                                </span>
                                <span v-if="!addSpaceMemberForm.user_ids.length" class="text-muted fs-12 ms-2">
                                    Klik untuk memilih anggota workspace...
                                </span>
                            </div>

                            <!-- Dropdown Menu List -->
                            <div
                                v-if="isSpaceUserDropdownOpen"
                                class="position-absolute start-0 end-0 mt-1 bg-body border border-secondary-subtle rounded-3 shadow-lg p-2 z-3"
                                style="top: 100%; max-height: 240px; overflow-y: auto;"
                            >
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-transparent border-end-0">
                                        <i class="ri-search-line text-muted"></i>
                                    </span>
                                    <input
                                        v-model="spaceUserSearchQuery"
                                        type="text"
                                        class="form-control border-start-0 fs-12"
                                        placeholder="Cari anggota..."
                                        autofocus
                                        @click.stop
                                    />
                                </div>

                                <div class="d-flex flex-column gap-1">
                                    <div
                                        v-for="u in filteredAvailableWorkspaceUsers"
                                        :key="u.id"
                                        class="d-flex align-items-center justify-content-between p-2 rounded-2 cursor-pointer transition-all hover-bg-item"
                                        :class="{ 'bg-primary-subtle text-primary': addSpaceMemberForm.user_ids.includes(u.id) }"
                                        @click.stop="toggleSelectSpaceUser(u)"
                                    >
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <div
                                                class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold fs-11 flex-shrink-0"
                                                :class="addSpaceMemberForm.user_ids.includes(u.id) ? 'bg-primary' : 'bg-secondary'"
                                                style="width: 26px; height: 26px;"
                                            >
                                                {{ u.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="fs-12 fw-semibold text-truncate text-body">{{ u.name }}</div>
                                                <div class="fs-10 text-muted text-truncate">{{ u.email }}</div>
                                            </div>
                                        </div>
                                        <i
                                            :class="addSpaceMemberForm.user_ids.includes(u.id) ? 'ri-checkbox-circle-fill text-primary fs-16' : 'ri-checkbox-blank-circle-line text-muted fs-16'"
                                        ></i>
                                    </div>
                                    <div v-if="!filteredAvailableWorkspaceUsers.length" class="text-center py-2 text-muted fs-12">
                                        Tidak ada anggota yang cocok.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fs-12 fw-semibold mb-1">Peran di Space</label>
                            <select v-model="addSpaceMemberForm.role" class="form-select form-select-sm rounded-2">
                                <option value="member">Anggota (Member)</option>
                                <option value="manager">Manajer Space</option>
                                <option value="viewer">Pengamat (Viewer)</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <button
                                type="submit"
                                class="btn btn-primary btn-sm w-100 fw-semibold rounded-pill"
                                :disabled="addSpaceMemberForm.processing || !addSpaceMemberForm.user_ids.length"
                            >
                                <i class="ri-user-add-line me-1"></i> Tambah
                            </button>
                        </div>
                    </form>
                </div>
                <div v-else class="alert alert-info py-2 px-3 mb-0 fs-12 d-flex align-items-center gap-2">
                    <i class="ri-information-line fs-16 text-primary"></i>
                    <span>Semua anggota di workspace ini sudah terdaftar di Space ini.</span>
                </div>
            </div>

            <!-- Grid Anggota Terdaftar -->
            <div class="row g-3">
                <div v-for="member in localSpace.members" :key="member.id" class="col-md-4">
                    <div class="border border-secondary-subtle rounded-3 p-3 d-flex align-items-center justify-content-between gap-2 bg-body shadow-2xs">
                        <div class="d-flex align-items-center gap-3 overflow-hidden">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fs-16 fw-semibold flex-shrink-0" style="width: 42px; height: 42px;">
                                {{ member.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="overflow-hidden">
                                <h6 class="fw-bold mb-0 fs-14 text-truncate text-body">{{ member.name }}</h6>
                                <span class="fs-12 text-muted text-truncate d-block">{{ member.email }}</span>
                                <div class="badge bg-primary-subtle text-primary rounded-pill mt-1 fs-10 text-capitalize">
                                    {{ member.pivot?.role || 'Member' }}
                                </div>
                            </div>
                        </div>
                        <button
                            v-if="!isViewer"
                            type="button"
                            class="btn btn-sm btn-link text-danger p-1 border-0 flex-shrink-0"
                            title="Keluarkan dari Space"
                            @click="removeSpaceMember(member)"
                        >
                            <i class="ri-delete-bin-line fs-15"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: SETTINGS -->
        <div v-else-if="activeTab === 'settings' && !isViewer" class="card border border-secondary-subtle rounded-3 p-4 shadow-sm" style="max-width: 600px;">
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

            <!-- Zona Bahaya / Hapus Space -->
            <div class="card border border-danger-subtle bg-danger-subtle bg-opacity-10 rounded-3 p-4 mt-4 shadow-sm">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="ri-error-warning-fill text-danger fs-18"></i>
                    <h6 class="fw-bold text-danger mb-0 fs-14">Zona Bahaya</h6>
                </div>
                <p class="text-muted fs-12 mb-3">
                    Menghapus Space ini akan menghapus seluruh papan Kanban, daftar tugas, komentar diskusi, dan lampiran berkas secara permanen. Tindakan ini tidak dapat dibatalkan.
                </p>
                <div>
                    <button
                        type="button"
                        class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold shadow-xs"
                        @click="deleteCurrentSpace"
                    >
                        <i class="ri-delete-bin-line me-1"></i> Hapus Space Ini
                    </button>
                </div>
            </div>
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
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable m-2 m-sm-auto">
                <div class="modal-content rounded-3 border-0 shadow-lg">
                    <!-- Modal Header -->
                    <div class="modal-header border-bottom px-3 px-sm-4 py-3 bg-light-subtle">
                        <div class="d-flex align-items-center gap-2 flex-grow-1 me-3">
                            <span class="badge rounded-pill px-2 py-1 fs-11 text-uppercase" :class="priorityColor(taskPriority)">
                                {{ taskPriority }}
                            </span>
                            <input
                                v-model="taskTitle"
                                type="text"
                                class="form-control form-control-lg border-0 bg-transparent fw-bold fs-16 p-0 text-body"
                                placeholder="Judul task..."
                                :readonly="isViewer"
                                :disabled="isViewer"
                                @blur="saveTaskDetails"
                            />
                        </div>
                        <button type="button" class="btn-close" @click="closeTaskModal"></button>
                    </div>

                    <!-- Modal Body (Two-column layout) -->
                    <div class="modal-body p-3 p-sm-4">
                        <!-- Viewer Read-Only Notice -->
                        <div v-if="isViewer" class="alert alert-info py-2 px-3 fs-12 mb-3 d-flex align-items-center gap-2 rounded-3 border-0 bg-info-subtle text-info-emphasis">
                            <i class="ri-eye-line fs-16"></i>
                            <span>Anda sedang membuka task ini dalam mode <strong>Viewer (Hanya Baca)</strong>. Anda dapat melihat seluruh rincian dan mengirim pesan di tab <strong>Diskusi & Log</strong>.</span>
                        </div>

                        <div class="row g-4">
                            <!-- Left Column: Description, Checklists, Attachments, Comments -->
                            <div class="col-lg-8">
                                <!-- Tabs Navigation inside modal -->
                                <ul class="nav nav-pills mb-4 bg-light p-1 rounded-pill w-fit border flex-nowrap overflow-x-auto text-nowrap">
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
                                            :readonly="isViewer"
                                            :disabled="isViewer"
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
                                                        :disabled="isViewer"
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
                                                    v-if="!isViewer"
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
                                        <form v-if="!isViewer" @submit.prevent="addChecklist" class="d-flex gap-2">
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

                                    <!-- Dependensi Tugas (Task Dependencies) Subsystem -->
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fs-13 fw-bold text-muted text-uppercase mb-0">
                                                <i class="ri-links-line me-1"></i> Dependensi Tugas
                                            </label>
                                            <span class="fs-12 text-muted fw-semibold">
                                                {{ (selectedTask?.dependencies?.length || 0) + (selectedTask?.dependents?.length || 0) }} Kaitan
                                            </span>
                                        </div>

                                        <!-- 1. Prerequisite Tasks (Task ini bergantung pada...) -->
                                        <div class="card border rounded-3 p-3 mb-3 bg-light-subtle">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fs-12 fw-bold text-body d-flex align-items-center gap-1">
                                                    <i class="ri-arrow-left-line text-warning"></i> Tugas Prasyarat (Harus selesai sebelum task ini)
                                                </span>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill fs-11">
                                                    {{ selectedTask?.dependencies?.length || 0 }}
                                                </span>
                                            </div>

                                            <!-- List of Prerequisites -->
                                            <div v-if="selectedTask?.dependencies?.length" class="d-flex flex-column gap-2 mb-2">
                                                <div
                                                    v-for="dep in selectedTask.dependencies"
                                                    :key="dep.id"
                                                    class="d-flex align-items-center justify-content-between p-2 rounded-2 border bg-body"
                                                >
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1">
                                                        <span class="badge rounded-pill px-2 py-0 fs-10 text-uppercase" :class="priorityColor(dep.priority)">
                                                            {{ dep.priority }}
                                                        </span>
                                                        <span class="fs-13 fw-semibold text-body text-truncate cursor-pointer hover-text-primary" @click="openTaskModal(dep)" :title="dep.title">
                                                            {{ dep.title }}
                                                        </span>
                                                        <span v-if="dep.due_at" class="fs-11 text-muted text-nowrap ms-auto me-2">
                                                            <i class="ri-calendar-event-line me-1"></i>Tenggat: {{ formatDateShort(dep.due_at) }}
                                                        </span>
                                                    </div>
                                                    <button
                                                        v-if="!isViewer"
                                                        type="button"
                                                        class="btn btn-sm btn-link text-danger p-0 border-0 flex-shrink-0"
                                                        title="Hapus dependensi prasyarat"
                                                        @click="removeDependency(dep.id)"
                                                    >
                                                        <i class="ri-close-line fs-16"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div v-else class="text-muted fs-12 py-1 mb-2">
                                                Belum ada tugas prasyarat. Task ini dapat dikerjakan secara mandiri.
                                            </div>

                                            <!-- Add Prerequisite Dropdown (if not viewer) -->
                                            <div v-if="!isViewer && availablePrerequisiteTasks.length > 0" class="d-flex flex-wrap gap-2 align-items-center mt-2 pt-2 border-top">
                                                <select v-model="selectedPrerequisiteTaskId" class="form-select form-select-sm rounded-pill fs-12 flex-grow-1" style="min-width: 180px;">
                                                    <option value="">+ Pilih tugas prasyarat dari Space ini...</option>
                                                    <option v-for="t in availablePrerequisiteTasks" :key="t.id" :value="t.id">
                                                        {{ t.title }} ({{ t.stage_name || t.stage?.name || 'Status' }})
                                                    </option>
                                                </select>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary rounded-pill px-3 fs-12 fw-semibold text-nowrap"
                                                    :disabled="!selectedPrerequisiteTaskId"
                                                    @click="addDependency(selectedPrerequisiteTaskId)"
                                                >
                                                    <i class="ri-add-line me-1"></i> Tambah
                                                </button>
                                            </div>
                                        </div>

                                        <!-- 2. Dependent Tasks (Tugas yang bergantung pada tugas ini) -->
                                        <div class="card border rounded-3 p-3 bg-light-subtle">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <span class="fs-12 fw-bold text-body d-flex align-items-center gap-1">
                                                    <i class="ri-arrow-right-line text-info"></i> Tugas yang Bergantung (Menunggu task ini selesai)
                                                </span>
                                                <span class="badge bg-secondary-subtle text-secondary rounded-pill fs-11">
                                                    {{ selectedTask?.dependents?.length || 0 }}
                                                </span>
                                            </div>

                                            <div v-if="selectedTask?.dependents?.length" class="d-flex flex-column gap-2">
                                                <div
                                                    v-for="dep in selectedTask.dependents"
                                                    :key="dep.id"
                                                    class="d-flex align-items-center justify-content-between p-2 rounded-2 border bg-body"
                                                >
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1">
                                                        <span class="badge rounded-pill px-2 py-0 fs-10 text-uppercase" :class="priorityColor(dep.priority)">
                                                            {{ dep.priority }}
                                                        </span>
                                                        <span class="fs-13 fw-semibold text-body text-truncate cursor-pointer hover-text-primary" @click="openTaskModal(dep)" :title="dep.title">
                                                            {{ dep.title }}
                                                        </span>
                                                        <span v-if="dep.start_date" class="fs-11 text-muted text-nowrap ms-auto">
                                                            <i class="ri-calendar-line me-1"></i>Mulai: {{ formatDateShort(dep.start_date) }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div v-else class="text-muted fs-12 py-1">
                                                Tidak ada tugas lain yang menunggu penyelesaian task ini.
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Attachments Subsystem -->
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fs-13 fw-bold text-muted text-uppercase mb-0">
                                                <i class="ri-attachment-2 me-1"></i> Lampiran Berkas
                                            </label>
                                            <button
                                                v-if="!isViewer"
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
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden flex-grow-1">
                                                        <!-- Thumbnail / Icon based on type -->
                                                        <div
                                                            v-if="isImageFile(att)"
                                                            class="flex-shrink-0 cursor-pointer"
                                                            @click="openFilePreview(att)"
                                                            title="Klik untuk lihat pratinjau gambar"
                                                        >
                                                            <img
                                                                :src="`/tasks/${selectedTask.id}/attachments/${att.id}/preview`"
                                                                class="rounded border object-fit-cover shadow-2xs"
                                                                style="width: 38px; height: 38px;"
                                                                alt="Thumbnail"
                                                            />
                                                        </div>
                                                        <div
                                                            v-else-if="isPdfFile(att)"
                                                            class="flex-shrink-0 cursor-pointer"
                                                            @click="openFilePreview(att)"
                                                            title="Klik untuk lihat pratinjau PDF"
                                                        >
                                                            <div class="bg-danger-subtle text-danger rounded d-flex align-items-center justify-content-center shadow-2xs" style="width: 38px; height: 38px;">
                                                                <i class="ri-file-pdf-fill fs-20"></i>
                                                            </div>
                                                        </div>
                                                        <div v-else class="flex-shrink-0">
                                                            <div class="bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                                                <i class="ri-file-text-line fs-20"></i>
                                                            </div>
                                                        </div>

                                                        <div class="overflow-hidden">
                                                            <div class="fw-semibold fs-12 text-truncate" :title="att.original_name">
                                                                {{ att.original_name }}
                                                            </div>
                                                            <div class="fs-10 text-muted">
                                                                {{ formatFileSize(att.size_bytes) }} • {{ att.uploader?.name || 'User' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-2">
                                                        <!-- Preview Button for PDF & Images -->
                                                        <button
                                                            v-if="isImageFile(att) || isPdfFile(att)"
                                                            type="button"
                                                            class="btn btn-sm btn-light p-1 rounded-circle text-primary"
                                                            title="Pratinjau Berkas"
                                                            @click="openFilePreview(att)"
                                                        >
                                                            <i class="ri-eye-line fs-14"></i>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-light p-1 rounded-circle"
                                                            title="Unduh Berkas"
                                                            @click="downloadAttachment(att)"
                                                        >
                                                            <i class="ri-download-2-line fs-14"></i>
                                                        </button>
                                                        <button
                                                            v-if="!isViewer"
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
                                                        <span class="fs-11 text-muted">{{ formatDateTime(com.created_at) }}</span>
                                                    </div>
                                                    <button
                                                        v-if="com.user_id === currentUser?.id || currentUser?.is_super_admin"
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
                                                        <span v-if="act.action === 'timeline_adjusted' && act.metadata.dates_summary">
                                                            <i class="ri-calendar-event-line text-info me-1"></i>Timeline diubah: <strong>{{ act.metadata.dates_summary }}</strong>
                                                        </span>
                                                        <span v-else-if="act.action === 'timeline_cascaded'">
                                                            <i class="ri-git-merge-line text-primary me-1"></i>Timeline bergeser otomatis mengikuti prasyarat <strong>{{ act.metadata.prerequisite_title }}</strong>
                                                            <span v-if="act.metadata.dates_summary"> ({{ act.metadata.dates_summary }})</span>
                                                        </span>
                                                        <span v-else-if="act.action === 'dependency_added' && act.metadata.prerequisite_title">
                                                            <i class="ri-links-line text-indigo me-1"></i>Menautkan dependensi pada tugas: <strong>{{ act.metadata.prerequisite_title }}</strong>
                                                        </span>
                                                        <span v-else-if="act.action === 'dependency_removed'">
                                                            <i class="ri-link-unlink-m text-secondary me-1"></i>Menghapus dependensi tugas
                                                        </span>
                                                        <span v-else-if="act.metadata.new_stage">Pindah ke: <strong>{{ act.metadata.new_stage }}</strong></span>
                                                        <span v-else-if="act.metadata.new_priority">Prioritas diubah ke: <strong>{{ act.metadata.new_priority }}</strong></span>
                                                        <span v-else-if="act.metadata.checklist_title">Checklist: {{ act.metadata.checklist_title }}</span>
                                                        <span v-else-if="act.metadata.file_name">Berkas: {{ act.metadata.file_name }}</span>
                                                    </div>
                                                </div>
                                                <span class="fs-10 text-muted">{{ formatDateTime(act.created_at) }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-muted fs-11">
                                            Tidak ada riwayat aktivitas yang tercatat.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Column: Sidebar Attributes -->
                            <div class="col-lg-4 border-start-lg border-top border-top-lg-0 pt-4 pt-lg-0">
                                <div class="d-flex flex-column gap-3">
                                    <!-- Stage / Status -->
                                    <div>
                                        <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Status / Tahapan</label>
                                        <select
                                            v-model="taskStageId"
                                            class="form-select form-select-sm rounded-2"
                                            :disabled="isViewer"
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
                                            :disabled="isViewer"
                                            @change="saveTaskDetails"
                                        >
                                            <option value="low">🟢 Low</option>
                                            <option value="medium">🟡 Medium</option>
                                            <option value="high">🔴 High</option>
                                            <option value="urgent">🟣 Urgent</option>
                                        </select>
                                    </div>

                                    <!-- Start Date & Due Date -->
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Mulai</label>
                                            <input
                                                v-model="taskStartDate"
                                                type="date"
                                                class="form-control form-control-sm rounded-2"
                                                :disabled="isViewer"
                                                @change="saveTaskDetails"
                                            />
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-1">Tenggat</label>
                                            <input
                                                v-model="taskDueAt"
                                                type="date"
                                                class="form-control form-control-sm rounded-2"
                                                :disabled="isViewer"
                                                @change="saveTaskDetails"
                                            />
                                        </div>
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
                                            :disabled="isViewer"
                                            @blur="saveTaskDetails"
                                        />
                                    </div>

                                    <!-- Assignees (Anggota Penanggung Jawab) - Select2 Style -->
                                    <div class="position-relative">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <label class="form-label fs-12 fw-bold text-muted text-uppercase mb-0">
                                                Penanggung Jawab (Assignees)
                                            </label>
                                            <span class="fs-11 text-muted">{{ taskAssigneeIds.length }} dipilih</span>
                                        </div>

                                        <!-- Select2-style Box Container -->
                                        <div
                                            class="form-control form-control-sm p-1 d-flex flex-wrap align-items-center gap-1 bg-light-subtle rounded-2 min-h-38"
                                            :class="{
                                                'border-primary shadow-xs': isAssigneeDropdownOpen,
                                                'cursor-pointer': !isViewer,
                                                'opacity-75 bg-light cursor-not-allowed': isViewer
                                            }"
                                            @click="!isViewer && (isAssigneeDropdownOpen = !isAssigneeDropdownOpen)"
                                        >
                                            <!-- Selected Assignee Badges/Pills -->
                                            <span
                                                v-for="member in selectedAssigneeObjects"
                                                :key="member.id"
                                                class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill py-1 px-2 d-flex align-items-center gap-1 fs-11"
                                                @click.stop
                                            >
                                                <span
                                                    class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
                                                    style="width: 16px; height: 16px; font-size: 9px;"
                                                >
                                                    {{ member.name.charAt(0).toUpperCase() }}
                                                </span>
                                                <span class="text-truncate" style="max-width: 110px;">{{ member.name }}</span>
                                                <i
                                                    v-if="!isViewer"
                                                    class="ri-close-line cursor-pointer fs-12 text-muted hover-text-danger"
                                                    title="Hapus penanggung jawab"
                                                    @click.stop="removeAssignee(member.id)"
                                                ></i>
                                            </span>

                                            <!-- Placeholder when none selected -->
                                            <span
                                                v-if="!selectedAssigneeObjects.length && !isAssigneeDropdownOpen"
                                                class="text-muted fs-12 ps-2"
                                            >
                                                Pilih penanggung jawab...
                                            </span>

                                            <!-- Caret Indicator -->
                                            <div v-if="!isViewer" class="ms-auto pe-2 text-muted">
                                                <i :class="isAssigneeDropdownOpen ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'"></i>
                                            </div>
                                        </div>

                                        <!-- Select2 Dropdown Menu -->
                                        <div
                                            v-if="isAssigneeDropdownOpen && !isViewer"
                                            class="card position-absolute w-100 mt-1 border shadow-lg rounded-3 p-2 bg-body"
                                            style="z-index: 1070; max-height: 260px;"
                                            @click.stop
                                        >
                                            <!-- Search Filter Input -->
                                            <div class="input-group input-group-sm mb-2">
                                                <span class="input-group-text bg-transparent border-end-0">
                                                    <i class="ri-search-line text-muted fs-12"></i>
                                                </span>
                                                <input
                                                    v-model="assigneeSearchQuery"
                                                    type="text"
                                                    class="form-control border-start-0 fs-12"
                                                    placeholder="Cari anggota tim..."
                                                    autofocus
                                                />
                                                <button
                                                    v-if="assigneeSearchQuery"
                                                    type="button"
                                                    class="btn btn-sm btn-link text-muted border border-start-0 p-1"
                                                    @click="assigneeSearchQuery = ''"
                                                >
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </div>

                                            <!-- Members Option List -->
                                            <div class="d-flex flex-column gap-1 overflow-auto pe-1" style="max-height: 160px;">
                                                <div
                                                    v-for="member in filteredMembers"
                                                    :key="member.id"
                                                    class="d-flex align-items-center justify-content-between p-1 px-2 rounded-2 cursor-pointer transition-all hover-bg-light"
                                                    :class="{ 'bg-primary-subtle text-primary': taskAssigneeIds.includes(member.id) }"
                                                    @click="toggleAssignee(member.id)"
                                                >
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                        <div
                                                            class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold fs-11 flex-shrink-0"
                                                            :class="taskAssigneeIds.includes(member.id) ? 'bg-primary' : 'bg-secondary'"
                                                            style="width: 24px; height: 24px;"
                                                        >
                                                            {{ member.name.charAt(0).toUpperCase() }}
                                                        </div>
                                                        <div class="overflow-hidden">
                                                            <div class="fs-12 fw-semibold text-truncate">{{ member.name }}</div>
                                                            <div class="fs-10 text-muted text-truncate">{{ member.email }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="flex-shrink-0 ms-2">
                                                        <i
                                                            v-if="taskAssigneeIds.includes(member.id)"
                                                            class="ri-checkbox-circle-fill text-primary fs-16"
                                                        ></i>
                                                        <i
                                                            v-else
                                                            class="ri-checkbox-blank-circle-line text-muted fs-16"
                                                        ></i>
                                                    </div>
                                                </div>

                                                <div v-if="!filteredMembers.length" class="text-center py-3 text-muted fs-12">
                                                    Tidak ada anggota ditemukan.
                                                </div>
                                            </div>

                                            <!-- Dropdown Footer -->
                                            <div class="border-top pt-2 mt-1 d-flex justify-content-between align-items-center">
                                                <span class="fs-10 text-muted">{{ filteredMembers.length }} anggota</span>
                                                <button
                                                    type="button"
                                                    class="btn btn-xs btn-light rounded-pill px-2 py-0 fs-11 text-muted"
                                                    @click="isAssigneeDropdownOpen = false"
                                                >
                                                    Selesai
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="my-2" />

                                    <!-- Action Buttons -->
                                    <div v-if="!isViewer" class="d-flex flex-column gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-primary rounded-pill w-100 fw-semibold fs-13 py-2"
                                            :disabled="isSavingTask"
                                            @click="saveTaskDetails(true)"
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

        <!-- ============================================================ -->
        <!-- FILE PREVIEW MODAL (IN-APP PDF & IMAGE VIEWER)               -->
        <!-- ============================================================ -->
        <div v-if="isPreviewModalOpen && previewingFile" class="modal-backdrop fade show" style="z-index: 1060;" @click="closeFilePreview"></div>

        <div
            v-if="isPreviewModalOpen && previewingFile"
            class="modal fade show d-block"
            tabindex="-1"
            role="dialog"
            style="z-index: 1065;"
        >
            <div class="modal-dialog modal-xl modal-dialog-centered" :class="{ 'modal-lg': isImageFile(previewingFile) }">
                <div class="modal-content rounded-3 border-0 shadow-lg overflow-hidden">
                    <!-- Modal Header -->
                    <div class="modal-header py-3 px-4 bg-light border-bottom d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-2 overflow-hidden me-3">
                            <i v-if="isPdfFile(previewingFile)" class="ri-file-pdf-fill fs-22 text-danger flex-shrink-0"></i>
                            <i v-else-if="isImageFile(previewingFile)" class="ri-image-line fs-22 text-success flex-shrink-0"></i>
                            <i v-else class="ri-file-line fs-22 text-primary flex-shrink-0"></i>
                            <div class="overflow-hidden">
                                <h6 class="modal-title fw-bold fs-14 text-truncate mb-0" :title="previewingFile.original_name">
                                    {{ previewingFile.original_name }}
                                </h6>
                                <span class="fs-11 text-muted">{{ formatFileSize(previewingFile.size_bytes) }} • Diunggah oleh {{ previewingFile.uploader?.name || 'User' }}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary rounded-pill px-3 fs-12 fw-semibold"
                                @click="downloadAttachment(previewingFile)"
                            >
                                <i class="ri-download-2-line me-1"></i> Unduh
                            </button>
                            <button type="button" class="btn-close" @click="closeFilePreview"></button>
                        </div>
                    </div>

                    <!-- Modal Body: Render Image or PDF -->
                    <div class="modal-body p-3 bg-body-tertiary d-flex align-items-center justify-content-center" style="min-height: 420px; max-height: 80vh; overflow: auto;">
                        <!-- PDF Preview with iframe -->
                        <div v-if="isPdfFile(previewingFile)" class="w-100" style="height: 72vh;">
                            <iframe
                                :src="`/tasks/${selectedTask?.id}/attachments/${previewingFile.id}/preview`"
                                class="w-100 h-100 rounded border bg-white"
                                title="PDF Preview"
                            ></iframe>
                        </div>

                        <!-- Image Preview -->
                        <div v-else-if="isImageFile(previewingFile)" class="text-center p-2 w-100 d-flex justify-content-center">
                            <img
                                :src="`/tasks/${selectedTask?.id}/attachments/${previewingFile.id}/preview`"
                                class="img-fluid rounded shadow-sm border"
                                style="max-height: 72vh; object-fit: contain;"
                                :alt="previewingFile.original_name"
                            />
                        </div>

                        <!-- Fallback for other file types -->
                        <div v-else class="text-center p-5">
                            <i class="ri-file-warning-line fs-48 text-warning mb-2"></i>
                            <h6 class="fw-bold">Pratinjau langsung tidak tersedia untuk format berkas ini.</h6>
                            <p class="text-muted fs-12 mb-3">Silakan unduh berkas untuk membukanya di komputer Anda.</p>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold"
                                @click="downloadAttachment(previewingFile)"
                            >
                                <i class="ri-download-2-line me-1"></i> Unduh Berkas
                            </button>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="modal-footer py-2 px-4 border-top bg-light d-flex justify-content-between align-items-center">
                        <span class="fs-12 text-muted">
                            <i class="ri-information-line me-1"></i> Mode pratinjau berkas
                        </span>
                        <button type="button" class="btn btn-sm btn-secondary rounded-pill px-4" @click="closeFilePreview">
                            Tutup
                        </button>
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
.min-h-38 {
    min-height: 38px;
}
.hover-bg-light:hover {
    background-color: var(--bs-light) !important;
}
.hover-text-danger:hover {
    color: var(--bs-danger) !important;
}
.shadow-2xs {
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
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
