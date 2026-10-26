import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../auth/models/user_model.dart';
import '../../kanban/models/stage_model.dart';
import '../../kanban/models/task_model.dart';
import '../../kanban/repositories/kanban_repository.dart';

class TaskDetailModal extends StatefulWidget {
  final int taskId;
  final TaskModel? initialTask;
  final KanbanRepository kanbanRepo;
  final List<StageModel> stages;
  final List<UserModel> spaceMembers;
  final UserModel? currentUser;
  final VoidCallback? onTaskUpdated;

  const TaskDetailModal({
    super.key,
    required this.taskId,
    this.initialTask,
    required this.kanbanRepo,
    this.stages = const [],
    this.spaceMembers = const [],
    this.currentUser,
    this.onTaskUpdated,
  });

  /// Static helper to display this modal bottom sheet comfortably
  static Future<void> show(
    BuildContext context, {
    required int taskId,
    TaskModel? initialTask,
    required KanbanRepository kanbanRepo,
    List<StageModel> stages = const [],
    List<UserModel> spaceMembers = const [],
    UserModel? currentUser,
    VoidCallback? onTaskUpdated,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => TaskDetailModal(
        taskId: taskId,
        initialTask: initialTask,
        kanbanRepo: kanbanRepo,
        stages: stages,
        spaceMembers: spaceMembers,
        currentUser: currentUser,
        onTaskUpdated: onTaskUpdated,
      ),
    );
  }

  @override
  State<TaskDetailModal> createState() => _TaskDetailModalState();
}

class _TaskDetailModalState extends State<TaskDetailModal>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  bool _isLoading = true;
  bool _isSavingDetails = false;
  bool _isSendingComment = false;
  bool _isAddingChecklist = false;
  bool _isUploadingAttachment = false;

  TaskModel? _task;
  late TextEditingController _titleCtrl;
  late TextEditingController _descCtrl;
  final TextEditingController _commentCtrl = TextEditingController();
  final TextEditingController _checklistCtrl = TextEditingController();

  late int _selectedStageId;
  late String _selectedPriority;
  String? _selectedStartDate;
  String? _selectedDueDate;
  List<int> _selectedAssigneeIds = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);

    _titleCtrl = TextEditingController(text: widget.initialTask?.title ?? '');
    _descCtrl =
        TextEditingController(text: widget.initialTask?.description ?? '');
    _selectedStageId = widget.initialTask?.stageId ??
        (widget.stages.isNotEmpty ? widget.stages.first.id : 0);
    _selectedPriority = widget.initialTask?.priority ?? 'medium';
    _selectedStartDate = widget.initialTask?.startDate;
    _selectedDueDate = widget.initialTask?.dueAt;
    _selectedAssigneeIds =
        widget.initialTask?.assignees.map((u) => u.id).toList() ?? [];

    if (widget.initialTask != null) {
      _task = widget.initialTask;
      _isLoading = false;
    }

    _loadFullTaskDetail();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _titleCtrl.dispose();
    _descCtrl.dispose();
    _commentCtrl.dispose();
    _checklistCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadFullTaskDetail() async {
    try {
      final task = await widget.kanbanRepo.getTaskDetail(widget.taskId);
      if (!mounted) return;
      setState(() {
        _task = task;
        _isLoading = false;
        _titleCtrl.text = task.title;
        _descCtrl.text = task.description ?? '';
        _selectedStageId = task.stageId;
        _selectedPriority = task.priority;
        _selectedStartDate = task.startDate;
        _selectedDueDate = task.dueAt;
        _selectedAssigneeIds = task.assignees.map((u) => u.id).toList();
      });
    } catch (_) {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  Color _priorityColor(String priority) {
    switch (priority.toLowerCase()) {
      case 'urgent':
        return Colors.purple;
      case 'high':
        return Colors.red;
      case 'medium':
        return Colors.amber.shade800;
      case 'low':
      default:
        return Colors.green;
    }
  }

  String _formatFileSize(int bytes) {
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  String _formatDateString(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return 'Pilih Tanggal';
    try {
      final parsed = DateTime.parse(dateStr);
      return DateFormat('dd MMM yyyy').format(parsed);
    } catch (_) {
      return dateStr;
    }
  }

  // --- SAVE TASK DETAILS ---
  Future<void> _saveTaskDetails() async {
    final title = _titleCtrl.text.trim();
    if (title.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Judul tugas tidak boleh kosong')),
      );
      return;
    }

    setState(() => _isSavingDetails = true);

    try {
      // If stage changed, move task
      if (_task != null && _selectedStageId != _task!.stageId) {
        await widget.kanbanRepo.moveTask(
          taskId: widget.taskId,
          stageId: _selectedStageId,
          position: 1,
        );
      }

      final updated = await widget.kanbanRepo.updateTask(
        taskId: widget.taskId,
        title: title,
        description: _descCtrl.text.trim(),
        priority: _selectedPriority,
        startDate: _selectedStartDate,
        dueAt: _selectedDueDate,
        stageId: _selectedStageId,
        assigneeIds: _selectedAssigneeIds,
      );

      if (!mounted) return;
      setState(() {
        _task = updated;
        _isSavingDetails = false;
      });

      widget.onTaskUpdated?.call();

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Perubahan tugas berhasil disimpan'),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } catch (e) {
      if (mounted) {
        setState(() => _isSavingDetails = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal menyimpan tugas: $e')),
        );
      }
    }
  }

  // --- CHECKLISTS ACTIONS ---
  Future<void> _toggleChecklist(ChecklistModel chk) async {
    try {
      await widget.kanbanRepo.toggleChecklist(widget.taskId, chk.id);
      if (!mounted) return;

      // Update local state immediately
      setState(() {
        if (_task != null) {
          final updatedChecklists = _task!.checklists.map((c) {
            if (c.id == chk.id) {
              return c.copyWith(isCompleted: !c.isCompleted);
            }
            return c;
          }).toList();

          final completedCount =
              updatedChecklists.where((c) => c.isCompleted).length;

          _task = _task!.copyWith(
            checklists: updatedChecklists,
            completedChecklistsCount: completedCount,
            checklistsCount: updatedChecklists.length,
          );
        }
      });
      widget.onTaskUpdated?.call();
    } catch (_) {}
  }

  Future<void> _addChecklist() async {
    final title = _checklistCtrl.text.trim();
    if (title.isEmpty) return;

    setState(() => _isAddingChecklist = true);
    _checklistCtrl.clear();

    try {
      final newChk =
          await widget.kanbanRepo.addChecklist(widget.taskId, title);
      if (!mounted) return;

      setState(() {
        if (_task != null) {
          final updated = List<ChecklistModel>.from(_task!.checklists)..add(newChk);
          _task = _task!.copyWith(
            checklists: updated,
            checklistsCount: updated.length,
            completedChecklistsCount:
                updated.where((c) => c.isCompleted).length,
          );
        }
        _isAddingChecklist = false;
      });
      widget.onTaskUpdated?.call();
    } catch (_) {
      if (mounted) setState(() => _isAddingChecklist = false);
    }
  }

  Future<void> _deleteChecklist(int checklistId) async {
    try {
      await widget.kanbanRepo.deleteChecklist(widget.taskId, checklistId);
      if (!mounted) return;

      setState(() {
        if (_task != null) {
          final updated =
              _task!.checklists.where((c) => c.id != checklistId).toList();
          _task = _task!.copyWith(
            checklists: updated,
            checklistsCount: updated.length,
            completedChecklistsCount:
                updated.where((c) => c.isCompleted).length,
          );
        }
      });
      widget.onTaskUpdated?.call();
    } catch (_) {}
  }

  // --- COMMENTS ACTIONS ---
  Future<void> _sendComment() async {
    final content = _commentCtrl.text.trim();
    if (content.isEmpty || _isSendingComment) return;

    setState(() => _isSendingComment = true);
    _commentCtrl.clear();

    try {
      final newComment =
          await widget.kanbanRepo.addComment(widget.taskId, content);
      if (!mounted) return;

      setState(() {
        if (_task != null) {
          final updated = List<TaskCommentModel>.from(_task!.comments)
            ..insert(0, newComment);
          _task = _task!.copyWith(comments: updated);
        }
        _isSendingComment = false;
      });
      widget.onTaskUpdated?.call();
    } catch (e) {
      if (mounted) {
        setState(() => _isSendingComment = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mengirim komentar: $e')),
        );
      }
    }
  }

  Future<void> _deleteComment(int commentId) async {
    try {
      await widget.kanbanRepo.deleteComment(widget.taskId, commentId);
      if (!mounted) return;

      setState(() {
        if (_task != null) {
          final updated =
              _task!.comments.where((c) => c.id != commentId).toList();
          _task = _task!.copyWith(comments: updated);
        }
      });
      widget.onTaskUpdated?.call();
    } catch (_) {}
  }

  // --- ATTACHMENTS ACTIONS ---
  Future<void> _deleteAttachment(int attachmentId) async {
    try {
      await widget.kanbanRepo.deleteAttachment(widget.taskId, attachmentId);
      if (!mounted) return;

      setState(() {
        if (_task != null) {
          final updated =
              _task!.attachments.where((a) => a.id != attachmentId).toList();
          _task = _task!.copyWith(attachments: updated);
        }
      });
      widget.onTaskUpdated?.call();
    } catch (_) {}
  }

  void _showUploadDialog() {
    final fileNameCtrl = TextEditingController(text: 'catatan_proyek.txt');
    final contentCtrl = TextEditingController();

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Unggah Berkas / Dokumen'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Masukkan nama dan isi berkas teks untuk disimpan ke task ini:',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: fileNameCtrl,
              decoration: const InputDecoration(
                labelText: 'Nama Berkas',
                hintText: 'misal: spek_desain.txt',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: contentCtrl,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Isi Dokumen / Teks',
                hintText: 'Tulis ringkasan atau catatan berkas...',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton.icon(
            icon: const Icon(Icons.cloud_upload_rounded, size: 16),
            label: const Text('Unggah'),
            onPressed: () async {
              final fName = fileNameCtrl.text.trim();
              final text = contentCtrl.text;
              if (fName.isEmpty) return;
              Navigator.pop(ctx);

              setState(() => _isUploadingAttachment = true);
              try {
                final bytes = utf8.encode(text);
                final newAtt = await widget.kanbanRepo.uploadAttachmentBytes(
                  widget.taskId,
                  bytes,
                  fName,
                );

                if (!mounted) return;
                setState(() {
                  if (_task != null) {
                    final updated =
                        List<TaskAttachmentModel>.from(_task!.attachments)
                          ..insert(0, newAtt);
                    _task = _task!.copyWith(attachments: updated);
                  }
                  _isUploadingAttachment = false;
                });
                widget.onTaskUpdated?.call();
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Berkas berhasil diunggah')),
                );
              } catch (e) {
                if (mounted) {
                  setState(() => _isUploadingAttachment = false);
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Gagal mengunggah berkas: $e')),
                  );
                }
              }
            },
          ),
        ],
      ),
    );
  }

  // --- ASSIGNEES SELECTION DIALOG ---
  void _showAssigneePicker() {
    final members = widget.spaceMembers;
    if (members.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Tidak ada anggota space untuk dipilih')),
      );
      return;
    }

    final tempSelected = List<int>.from(_selectedAssigneeIds);

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setDlgState) => AlertDialog(
          title: const Text('Pilih Anggota Penanggung Jawab'),
          content: SizedBox(
            width: double.maxFinite,
            child: ListView.builder(
              shrinkWrap: true,
              itemCount: members.length,
              itemBuilder: (ctx, i) {
                final m = members[i];
                final isSelected = tempSelected.contains(m.id);
                return CheckboxListTile(
                  value: isSelected,
                  title: Text(m.name,
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(m.email, style: const TextStyle(fontSize: 11)),
                  secondary: CircleAvatar(
                    backgroundColor: const Color(0xFF00AAAD).withAlpha(30),
                    child: Text(
                      m.name.isNotEmpty ? m.name[0].toUpperCase() : '?',
                      style: const TextStyle(
                          color: Color(0xFF00AAAD),
                          fontWeight: FontWeight.bold),
                    ),
                  ),
                  onChanged: (val) {
                    setDlgState(() {
                      if (val == true) {
                        tempSelected.add(m.id);
                      } else {
                        tempSelected.remove(m.id);
                      }
                    });
                  },
                );
              },
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Batal'),
            ),
            ElevatedButton(
              onPressed: () {
                setState(() {
                  _selectedAssigneeIds = tempSelected;
                });
                Navigator.pop(ctx);
              },
              child: const Text('Pilih'),
            ),
          ],
        ),
      ),
    );
  }

  // --- DATE PICKER HELPER ---
  Future<void> _pickDate({required bool isStart}) async {
    DateTime initial = DateTime.now();
    final currentStr = isStart ? _selectedStartDate : _selectedDueDate;
    if (currentStr != null && currentStr.isNotEmpty) {
      final parsed = DateTime.tryParse(currentStr);
      if (parsed != null) initial = parsed;
    }

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(2020),
      lastDate: DateTime(2035),
    );

    if (picked != null) {
      final formatted = DateFormat('yyyy-MM-dd').format(picked);
      setState(() {
        if (isStart) {
          _selectedStartDate = formatted;
        } else {
          _selectedDueDate = formatted;
        }
      });
    }
  }

  void _confirmDeleteTask() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hapus Tugas?'),
        content: const Text(
            'Tugas ini dan seluruh subtask, komentar, serta lampirannya akan dihapus permanen.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red,
              foregroundColor: Colors.white,
            ),
            onPressed: () async {
              Navigator.pop(ctx); // Close dialog
              Navigator.pop(context); // Close TaskDetailModal
              try {
                await widget.kanbanRepo.deleteTask(widget.taskId);
                widget.onTaskUpdated?.call();
              } catch (_) {}
            },
            child: const Text('Hapus'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      height: MediaQuery.of(context).size.height * 0.90,
      margin: EdgeInsets.only(bottom: bottomInset),
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: const [
          BoxShadow(color: Colors.black26, blurRadius: 10, spreadRadius: 2),
        ],
      ),
      child: Column(
        children: [
          // Drag Handle
          const SizedBox(height: 8),
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.withAlpha(80),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          // Modal Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                Expanded(
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFF00AAAD).withAlpha(20),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          'Tugas #${widget.taskId}',
                          style: const TextStyle(
                            color: Color(0xFF00AAAD),
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      if (_task?.isDone == true)
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.green.withAlpha(20),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text(
                            'Selesai',
                            style: TextStyle(
                              color: Colors.green,
                              fontWeight: FontWeight.bold,
                              fontSize: 11,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.delete_outline_rounded,
                      color: Colors.redAccent, size: 20),
                  tooltip: 'Hapus Tugas',
                  onPressed: _confirmDeleteTask,
                ),
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF00AAAD),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 8),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  onPressed: _isSavingDetails ? null : _saveTaskDetails,
                  icon: _isSavingDetails
                      ? const SizedBox(
                          width: 14,
                          height: 14,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.check_rounded, size: 16),
                  label: const Text('Simpan',
                      style:
                          TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                ),
                const SizedBox(width: 4),
                IconButton(
                  icon: const Icon(Icons.close_rounded, size: 22),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),
          // Tab Bar
          TabBar(
            controller: _tabController,
            labelColor: const Color(0xFF00AAAD),
            unselectedLabelColor: Colors.grey,
            indicatorColor: const Color(0xFF00AAAD),
            indicatorWeight: 3,
            tabs: [
              Tab(
                icon: const Icon(Icons.assignment_outlined, size: 18),
                text: 'Rincian & Checklist (${_task?.checklists.length ?? 0})',
              ),
              Tab(
                icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18),
                text: 'Diskusi & Komentar (${_task?.comments.length ?? 0})',
              ),
            ],
          ),
          const Divider(height: 1),
          // Content Area
          Expanded(
            child: _isLoading && _task == null
                ? const Center(child: CircularProgressIndicator())
                : TabBarView(
                    controller: _tabController,
                    children: [
                      _buildDetailsAndChecklistsTab(),
                      _buildDiscussionAndCommentsTab(),
                    ],
                  ),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // TAB 1: RINCIAN & CHECKLIST
  // ==========================================
  Widget _buildDetailsAndChecklistsTab() {
    final checklists = _task?.checklists ?? [];
    final completedCount = checklists.where((c) => c.isCompleted).length;
    final totalCount = checklists.length;
    final progress = totalCount > 0 ? (completedCount / totalCount) : 0.0;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Title Input
        const Text(
          'Judul Tugas',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _titleCtrl,
          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
          decoration: InputDecoration(
            hintText: 'Tulis judul tugas...',
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            filled: true,
            fillColor: Theme.of(context).cardColor,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide(color: Colors.grey.withAlpha(50)),
            ),
          ),
        ),
        const SizedBox(height: 14),

        // Description Input
        const Text(
          'Deskripsi Tugas',
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _descCtrl,
          maxLines: 3,
          style: const TextStyle(fontSize: 14),
          decoration: InputDecoration(
            hintText: 'Tambahkan detail deskripsi tugas...',
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            filled: true,
            fillColor: Theme.of(context).cardColor,
            border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(10),
              borderSide: BorderSide(color: Colors.grey.withAlpha(50)),
            ),
          ),
        ),
        const SizedBox(height: 16),

        // Stage & Priority Row
        Row(
          children: [
            // Stage dropdown
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Tahapan Kolom',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    decoration: BoxDecoration(
                      color: Theme.of(context).cardColor,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.grey.withAlpha(50)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<int>(
                        value: widget.stages.any((s) => s.id == _selectedStageId)
                            ? _selectedStageId
                            : (widget.stages.isNotEmpty ? widget.stages.first.id : null),
                        isExpanded: true,
                        items: widget.stages.map((s) {
                          return DropdownMenuItem<int>(
                            value: s.id,
                            child: Text(s.name, style: const TextStyle(fontSize: 13)),
                          );
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) setState(() => _selectedStageId = val);
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            // Priority dropdown
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Prioritas',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                    decoration: BoxDecoration(
                      color: Theme.of(context).cardColor,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.grey.withAlpha(50)),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: _selectedPriority.toLowerCase(),
                        isExpanded: true,
                        items: ['low', 'medium', 'high', 'urgent'].map((p) {
                          final color = _priorityColor(p);
                          return DropdownMenuItem<String>(
                            value: p,
                            child: Row(
                              children: [
                                Container(
                                  width: 8,
                                  height: 8,
                                  decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                                ),
                                const SizedBox(width: 8),
                                Text(p.toUpperCase(),
                                    style: TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                        color: color)),
                              ],
                            ),
                          );
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) setState(() => _selectedPriority = val);
                        },
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),

        // Start Date & Due Date Row
        Row(
          children: [
            // Start Date
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Tanggal Mulai',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
                  const SizedBox(height: 6),
                  InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => _pickDate(isStart: true),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.grey.withAlpha(50)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.calendar_today_rounded, size: 16, color: Color(0xFF00AAAD)),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _selectedStartDate != null
                                  ? _formatDateString(_selectedStartDate)
                                  : 'Pilih...',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: _selectedStartDate != null ? FontWeight.w600 : FontWeight.normal,
                                color: _selectedStartDate != null ? null : Colors.grey,
                              ),
                            ),
                          ),
                          if (_selectedStartDate != null)
                            GestureDetector(
                              onTap: () => setState(() => _selectedStartDate = null),
                              child: const Icon(Icons.clear_rounded, size: 16, color: Colors.grey),
                            ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 12),
            // Due Date
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Tenggat Waktu',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
                  const SizedBox(height: 6),
                  InkWell(
                    borderRadius: BorderRadius.circular(10),
                    onTap: () => _pickDate(isStart: false),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: Colors.grey.withAlpha(50)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.event_available_rounded, size: 16, color: Colors.redAccent),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _selectedDueDate != null
                                  ? _formatDateString(_selectedDueDate)
                                  : 'Pilih...',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: _selectedDueDate != null ? FontWeight.w600 : FontWeight.normal,
                                color: _selectedDueDate != null ? null : Colors.grey,
                              ),
                            ),
                          ),
                          if (_selectedDueDate != null)
                            GestureDetector(
                              onTap: () => setState(() => _selectedDueDate = null),
                              child: const Icon(Icons.clear_rounded, size: 16, color: Colors.grey),
                            ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),

        // Assignees Selection
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Text(
              'Penanggung Jawab (Assignees)',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey),
            ),
            TextButton.icon(
              onPressed: _showAssigneePicker,
              icon: const Icon(Icons.person_add_alt_1_rounded, size: 16),
              label: const Text('Kelola Anggota', style: TextStyle(fontSize: 12)),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: Colors.grey.withAlpha(50)),
          ),
          child: _selectedAssigneeIds.isEmpty
              ? const Text(
                  'Belum ada penanggung jawab ditugaskan',
                  style: TextStyle(fontSize: 12, color: Colors.grey),
                )
              : Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  children: _selectedAssigneeIds.map((id) {
                    final member = widget.spaceMembers.firstWhere(
                      (m) => m.id == id,
                      orElse: () => UserModel(id: id, name: 'User #$id', email: ''),
                    );
                    return Chip(
                      avatar: CircleAvatar(
                        backgroundColor: const Color(0xFF00AAAD),
                        child: Text(
                          member.name.isNotEmpty ? member.name[0].toUpperCase() : '?',
                          style: const TextStyle(color: Colors.white, fontSize: 11),
                        ),
                      ),
                      label: Text(member.name, style: const TextStyle(fontSize: 12)),
                      onDeleted: () {
                        setState(() {
                          _selectedAssigneeIds.remove(id);
                        });
                      },
                      deleteIconColor: Colors.grey,
                    );
                  }).toList(),
                ),
        ),
        const SizedBox(height: 24),

        // Checklists Section
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Row(
              children: [
                Icon(Icons.checklist_rounded, size: 18, color: Color(0xFF00AAAD)),
                SizedBox(width: 8),
                Text(
                  'Subtask & Checklist',
                  style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            Text(
              '$completedCount/$totalCount Selesai (${(progress * 100).toInt()}%)',
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey),
            ),
          ],
        ),
        const SizedBox(height: 8),
        // Progress Bar
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: progress,
            minHeight: 6,
            backgroundColor: Colors.grey.withAlpha(40),
            color: progress == 1.0 ? Colors.green : const Color(0xFF00AAAD),
          ),
        ),
        const SizedBox(height: 12),

        // Checklist Items List
        if (checklists.isEmpty)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 16),
            alignment: Alignment.center,
            child: const Text(
              'Belum ada subtask/checklist. Tambahkan di bawah.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          )
        else
          ...checklists.map((chk) {
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.withAlpha(35)),
              ),
              child: ListTile(
                dense: true,
                contentPadding: const EdgeInsets.symmetric(horizontal: 8),
                leading: Checkbox(
                  value: chk.isCompleted,
                  activeColor: const Color(0xFF00AAAD),
                  onChanged: (_) => _toggleChecklist(chk),
                ),
                title: Text(
                  chk.title,
                  style: TextStyle(
                    fontSize: 13,
                    decoration: chk.isCompleted ? TextDecoration.lineThrough : null,
                    color: chk.isCompleted ? Colors.grey : null,
                  ),
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.close_rounded, size: 16, color: Colors.grey),
                  tooltip: 'Hapus subtask',
                  onPressed: () => _deleteChecklist(chk.id),
                ),
              ),
            );
          }),

        const SizedBox(height: 8),
        // Add Checklist Input
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _checklistCtrl,
                decoration: InputDecoration(
                  hintText: 'Tambah subtask/checklist...',
                  hintStyle: const TextStyle(fontSize: 13),
                  isDense: true,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  filled: true,
                  fillColor: Theme.of(context).cardColor,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(8),
                    borderSide: BorderSide(color: Colors.grey.withAlpha(50)),
                  ),
                ),
                onSubmitted: (_) => _addChecklist(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              icon: _isAddingChecklist
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.add_circle_rounded, color: Color(0xFF00AAAD), size: 28),
              tooltip: 'Tambah',
              onPressed: _isAddingChecklist ? null : _addChecklist,
            ),
          ],
        ),
        const SizedBox(height: 24),

        // Attachments Section
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                const Icon(Icons.attach_file_rounded, size: 18, color: Color(0xFF00AAAD)),
                const SizedBox(width: 8),
                Text(
                  'Lampiran Berkas (${_task?.attachments.length ?? 0})',
                  style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF00AAAD).withAlpha(20),
                foregroundColor: const Color(0xFF00AAAD),
                elevation: 0,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: _isUploadingAttachment ? null : _showUploadDialog,
              icon: const Icon(Icons.upload_file_rounded, size: 16),
              label: const Text('Unggah Berkas', style: TextStyle(fontSize: 12)),
            ),
          ],
        ),
        const SizedBox(height: 10),

        if (_isUploadingAttachment)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)),
                SizedBox(width: 10),
                Text('Mengunggah lampiran...', style: TextStyle(fontSize: 12, color: Colors.grey)),
              ],
            ),
          ),

        if ((_task?.attachments ?? []).isEmpty)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 16),
            alignment: Alignment.center,
            child: const Text(
              'Belum ada berkas terlampir pada tugas ini.',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
          )
        else
          ...(_task!.attachments).map((att) {
            return Container(
              margin: const EdgeInsets.only(bottom: 6),
              decoration: BoxDecoration(
                color: Theme.of(context).cardColor,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.grey.withAlpha(35)),
              ),
              child: ListTile(
                dense: true,
                leading: const CircleAvatar(
                  radius: 16,
                  backgroundColor: Color(0xFF00AAAD),
                  child: Icon(Icons.description_rounded, size: 16, color: Colors.white),
                ),
                title: Text(att.originalName,
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                subtitle: Text(
                  '${_formatFileSize(att.fileSize)} • ${att.mimeType}',
                  style: const TextStyle(fontSize: 11, color: Colors.grey),
                ),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline_rounded, color: Colors.redAccent, size: 18),
                  tooltip: 'Hapus berkas',
                  onPressed: () => _deleteAttachment(att.id),
                ),
              ),
            );
          }),

        const SizedBox(height: 20),
      ],
    );
  }

  // ==========================================
  // TAB 2: DISKUSI & KOMENTAR
  // ==========================================
  Widget _buildDiscussionAndCommentsTab() {
    final comments = _task?.comments ?? [];

    return Column(
      children: [
        // Comments List
        Expanded(
          child: comments.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.chat_bubble_outline_rounded,
                          size: 48, color: Colors.grey.withAlpha(120)),
                      const SizedBox(height: 12),
                      const Text(
                        'Belum ada komentar atau diskusi',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Mulai diskusi dengan tim terkait tugas ini.',
                        style: TextStyle(fontSize: 12, color: Colors.grey),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  reverse: false,
                  itemCount: comments.length,
                  itemBuilder: (ctx, idx) {
                    final c = comments[idx];
                    final isMyComment = widget.currentUser != null &&
                        c.userId == widget.currentUser!.id;

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.grey.withAlpha(35)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              CircleAvatar(
                                radius: 14,
                                backgroundColor: const Color(0xFF00AAAD).withAlpha(30),
                                child: Text(
                                  c.userName.isNotEmpty
                                      ? c.userName[0].toUpperCase()
                                      : '?',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFF00AAAD),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      c.userName,
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.bold,
                                      ),
                                    ),
                                    if (c.createdAt.isNotEmpty)
                                      Text(
                                        c.createdAt,
                                        style: const TextStyle(
                                            fontSize: 10, color: Colors.grey),
                                      ),
                                  ],
                                ),
                              ),
                              if (isMyComment)
                                IconButton(
                                  icon: const Icon(Icons.delete_outline_rounded,
                                      size: 16, color: Colors.grey),
                                  tooltip: 'Hapus komentar',
                                  padding: EdgeInsets.zero,
                                  constraints: const BoxConstraints(),
                                  onPressed: () => _deleteComment(c.id),
                                ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            c.content,
                            style: const TextStyle(fontSize: 13, height: 1.4),
                          ),
                        ],
                      ),
                    );
                  },
                ),
        ),

        // Bottom Input Field
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            border: Border(top: BorderSide(color: Colors.grey.withAlpha(35))),
          ),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _commentCtrl,
                  decoration: InputDecoration(
                    hintText: 'Tulis komentar diskusi...',
                    hintStyle: const TextStyle(fontSize: 13),
                    isDense: true,
                    contentPadding:
                        const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    filled: true,
                    fillColor: Theme.of(context).scaffoldBackgroundColor,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(20),
                      borderSide: BorderSide(color: Colors.grey.withAlpha(50)),
                    ),
                  ),
                  onSubmitted: (_) => _sendComment(),
                ),
              ),
              const SizedBox(width: 8),
              IconButton(
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0xFF00AAAD),
                  foregroundColor: Colors.white,
                ),
                icon: _isSendingComment
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                            strokeWidth: 2, color: Colors.white),
                      )
                    : const Icon(Icons.send_rounded, size: 18),
                tooltip: 'Kirim Komentar',
                onPressed: _isSendingComment ? null : _sendComment,
              ),
            ],
          ),
        ),
      ],
    );
  }
}
