import 'package:flutter/material.dart';
import '../../kanban/repositories/kanban_repository.dart';
import '../models/notification_model.dart';

class NotificationsSheet extends StatefulWidget {
  final KanbanRepository kanbanRepo;
  final void Function(NotificationModel notification)? onNotificationTapped;
  final VoidCallback? onNotificationsUpdated;

  const NotificationsSheet({
    super.key,
    required this.kanbanRepo,
    this.onNotificationTapped,
    this.onNotificationsUpdated,
  });

  /// Static helper to display this notifications bottom sheet
  static Future<void> show(
    BuildContext context, {
    required KanbanRepository kanbanRepo,
    void Function(NotificationModel notification)? onNotificationTapped,
    VoidCallback? onNotificationsUpdated,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => NotificationsSheet(
        kanbanRepo: kanbanRepo,
        onNotificationTapped: onNotificationTapped,
        onNotificationsUpdated: onNotificationsUpdated,
      ),
    );
  }

  @override
  State<NotificationsSheet> createState() => _NotificationsSheetState();
}

class _NotificationsSheetState extends State<NotificationsSheet> {
  bool _isLoading = true;
  bool _isMarkingAll = false;
  List<NotificationModel> _notifications = [];

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() => _isLoading = true);
    try {
      final list = await widget.kanbanRepo.getNotifications();
      if (!mounted) return;
      setState(() {
        _notifications = list;
        _isLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _markAllRead() async {
    if (_isMarkingAll) return;
    setState(() => _isMarkingAll = true);
    try {
      await widget.kanbanRepo.markAllNotificationsRead();
      if (!mounted) return;
      setState(() {
        _notifications = _notifications
            .map((n) => n.copyWith(readAt: DateTime.now().toIso8601String()))
            .toList();
        _isMarkingAll = false;
      });
      widget.onNotificationsUpdated?.call();
    } catch (_) {
      if (mounted) setState(() => _isMarkingAll = false);
    }
  }

  Future<void> _handleNotificationTap(NotificationModel notification) async {
    if (!notification.isRead) {
      try {
        await widget.kanbanRepo.markNotificationRead(notification.id);
        if (mounted) {
          setState(() {
            _notifications = _notifications.map((n) {
              if (n.id == notification.id) {
                return n.copyWith(readAt: DateTime.now().toIso8601String());
              }
              return n;
            }).toList();
          });
          widget.onNotificationsUpdated?.call();
        }
      } catch (_) {}
    }

    if (mounted) {
      Navigator.pop(context);
    }

    widget.onNotificationTapped?.call(notification);
  }

  IconData _iconForType(String type) {
    if (type.contains('comment')) {
      return Icons.chat_bubble_outline_rounded;
    } else if (type.contains('task')) {
      return Icons.assignment_outlined;
    } else if (type.contains('stage') || type.contains('move')) {
      return Icons.view_column_rounded;
    } else if (type.contains('attachment')) {
      return Icons.attach_file_rounded;
    }
    return Icons.notifications_none_rounded;
  }

  @override
  Widget build(BuildContext context) {
    final unreadCount = _notifications.where((n) => !n.isRead).length;

    return Container(
      height: MediaQuery.of(context).size.height * 0.75,
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        boxShadow: const [
          BoxShadow(color: Colors.black26, blurRadius: 10, spreadRadius: 2),
        ],
      ),
      child: Column(
        children: [
          // Drag handle
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
          // Sheet Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              children: [
                const Icon(Icons.notifications_active_rounded,
                    color: Color(0xFF00AAAD), size: 22),
                const SizedBox(width: 8),
                const Text(
                  'Notifikasi',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                if (unreadCount > 0) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: Colors.redAccent,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(
                      '$unreadCount Baru',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                ],
                const Spacer(),
                if (unreadCount > 0)
                  TextButton.icon(
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    ),
                    onPressed: _isMarkingAll ? null : _markAllRead,
                    icon: _isMarkingAll
                        ? const SizedBox(
                            width: 12,
                            height: 12,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.done_all_rounded, size: 16),
                    label: const Text(
                      'Tandai Semua Dibaca',
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                    ),
                  ),
                IconButton(
                  icon: const Icon(Icons.close_rounded, size: 20),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          // Notifications List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _notifications.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.notifications_off_outlined,
                                size: 56, color: Colors.grey.withAlpha(120)),
                            const SizedBox(height: 12),
                            const Text(
                              'Belum ada notifikasi',
                              style: TextStyle(
                                  fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'Semua pembaruan aktivitas dan tugas akan muncul di sini.',
                              style: TextStyle(color: Colors.grey, fontSize: 12),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _fetchNotifications,
                        child: ListView.separated(
                          padding: const EdgeInsets.all(12),
                          itemCount: _notifications.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (ctx, idx) {
                            final n = _notifications[idx];
                            final isUnread = !n.isRead;

                            return Card(
                              elevation: 0,
                              margin: EdgeInsets.zero,
                              color: isUnread
                                  ? const Color(0xFF00AAAD).withAlpha(15)
                                  : Theme.of(context).cardColor,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                                side: BorderSide(
                                  color: isUnread
                                      ? const Color(0xFF00AAAD).withAlpha(60)
                                      : Colors.grey.withAlpha(35),
                                ),
                              ),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(12),
                                onTap: () => _handleNotificationTap(n),
                                child: Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      CircleAvatar(
                                        radius: 18,
                                        backgroundColor: isUnread
                                            ? const Color(0xFF00AAAD)
                                            : Colors.grey.withAlpha(40),
                                        foregroundColor:
                                            isUnread ? Colors.white : Colors.grey.shade700,
                                        child: Icon(_iconForType(n.type), size: 18),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              n.title,
                                              style: TextStyle(
                                                fontSize: 13,
                                                fontWeight: isUnread
                                                    ? FontWeight.bold
                                                    : FontWeight.w600,
                                              ),
                                            ),
                                            if (n.message.isNotEmpty) ...[
                                              const SizedBox(height: 4),
                                              Text(
                                                n.message,
                                                maxLines: 2,
                                                overflow: TextOverflow.ellipsis,
                                                style: const TextStyle(
                                                  fontSize: 12,
                                                  color: Colors.grey,
                                                ),
                                              ),
                                            ],
                                            if (n.createdAt.isNotEmpty) ...[
                                              const SizedBox(height: 6),
                                              Text(
                                                n.createdAt,
                                                style: TextStyle(
                                                  fontSize: 10,
                                                  color: Colors.grey.shade500,
                                                ),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ),
                                      if (isUnread) ...[
                                        const SizedBox(width: 8),
                                        Container(
                                          width: 8,
                                          height: 8,
                                          decoration: const BoxDecoration(
                                            color: Color(0xFF00AAAD),
                                            shape: BoxShape.circle,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
