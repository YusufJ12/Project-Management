import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:nexsphere_mobile/core/network/api_client.dart';
import 'package:nexsphere_mobile/features/auth/models/user_model.dart';
import 'package:nexsphere_mobile/features/kanban/models/stage_model.dart';
import 'package:nexsphere_mobile/features/kanban/models/task_model.dart';
import 'package:nexsphere_mobile/features/kanban/repositories/kanban_repository.dart';
import 'package:nexsphere_mobile/features/notifications/models/notification_model.dart';
import 'package:nexsphere_mobile/features/notifications/presentation/notifications_sheet.dart';
import 'package:nexsphere_mobile/features/tasks/presentation/task_detail_modal.dart';

class MockKanbanRepository extends KanbanRepository {
  MockKanbanRepository() : super(ApiClient());

  @override
  Future<TaskModel> getTaskDetail(int taskId) async {
    return TaskModel(
      id: taskId,
      listId: 1,
      stageId: 1,
      title: 'Desain Wireframe Mobile',
      description: 'Tampilan interaktif Kanban dan Task Detail',
      priority: 'urgent',
      startDate: '2026-10-26',
      dueAt: '2026-11-05',
      position: 1,
      stageName: 'Review',
      isDone: false,
      checklists: [
        ChecklistModel(id: 1, title: 'Checklist 1', isCompleted: false),
        ChecklistModel(id: 2, title: 'Checklist 2', isCompleted: true),
      ],
      comments: [
        TaskCommentModel(
          id: 10,
          taskId: taskId,
          userId: 5,
          userName: 'Manager',
          content: 'Mohon selesaikan tepat waktu.',
          createdAt: '2026-10-26',
        ),
      ],
      attachments: [
        TaskAttachmentModel(
          id: 20,
          taskId: taskId,
          originalName: 'design.png',
          fileSize: 500000,
          mimeType: 'image/png',
          createdAt: '2026-10-26',
        ),
      ],
    );
  }

  @override
  Future<List<NotificationModel>> getNotifications() async {
    return [
      NotificationModel(
        id: 1,
        userId: 1,
        type: 'comment_added',
        title: 'Notifikasi Uji',
        message: 'Pesan notifikasi',
        createdAt: '2026-10-26',
      ),
    ];
  }
}

void main() {
  group('TaskModel & Sub-models Deserialization', () {
    test('parses rich TaskModel correctly from json', () {
      final json = {
        'id': 101,
        'list_id': 5,
        'stage_id': 12,
        'title': 'Implement Rich Task Detail',
        'description': 'Support attachments, comments, and checklists in Flutter',
        'priority': 'high',
        'start_date': '2026-10-26',
        'due_at': '2026-11-01',
        'estimate_minutes': 180,
        'position': 2,
        'stage_name': 'In Progress',
        'stage_color': '#00AAAD',
        'is_done': false,
        'assignees': [
          {
            'id': 1,
            'name': 'Budi Santoso',
            'email': 'budi@example.com',
            'avatar_path': '/avatars/1.png',
          },
          {
            'id': 2,
            'name': 'Siti Rahma',
            'email': 'siti@example.com',
          }
        ],
        'dependencies': [
          {'id': 100, 'title': 'Prerequisite task'}
        ],
        'comments': [
          {
            'id': 201,
            'task_id': 101,
            'content': 'Checklist progress is going well.',
            'created_at': '2026-10-26T10:00:00Z',
            'user': {
              'id': 1,
              'name': 'Budi Santoso',
              'avatar_path': '/avatars/1.png',
            }
          }
        ],
        'attachments': [
          {
            'id': 301,
            'task_id': 101,
            'original_name': 'specs.pdf',
            'size_bytes': 2048576,
            'detected_mime': 'application/pdf',
            'download_url': '/tasks/101/attachments/301/download',
            'created_at': '2026-10-26T09:30:00Z',
          }
        ],
        'checklists': [
          {
            'id': 401,
            'task_id': 101,
            'title': 'Unit tests for models',
            'is_completed': true,
          },
          {
            'id': 402,
            'task_id': 101,
            'title': 'Widget test for modal',
            'is_completed': false,
          }
        ],
      };

      final task = TaskModel.fromJson(json);

      expect(task.id, 101);
      expect(task.listId, 5);
      expect(task.stageId, 12);
      expect(task.title, 'Implement Rich Task Detail');
      expect(task.priority, 'high');
      expect(task.startDate, '2026-10-26');
      expect(task.dueAt, '2026-11-01');
      expect(task.estimateMinutes, 180);
      expect(task.isDone, false);

      expect(task.assignees.length, 2);
      expect(task.assignees[0].name, 'Budi Santoso');

      expect(task.dependencies.length, 1);

      expect(task.comments.length, 1);
      expect(task.comments[0].userName, 'Budi Santoso');
      expect(task.comments[0].content, 'Checklist progress is going well.');

      expect(task.attachments.length, 1);
      expect(task.attachments[0].originalName, 'specs.pdf');
      expect(task.attachments[0].fileSize, 2048576);
      expect(task.attachments[0].mimeType, 'application/pdf');

      expect(task.checklists.length, 2);
      expect(task.checklists[0].title, 'Unit tests for models');
      expect(task.checklists[0].isCompleted, true);
      expect(task.checklists[1].isCompleted, false);
      expect(task.completedChecklistsCount, 1);
      expect(task.checklistsCount, 2);
    });

    test('verifies TaskCommentModel and TaskAttachmentModel serialization', () {
      final comment = TaskCommentModel(
        id: 50,
        taskId: 10,
        userId: 2,
        userName: 'Citra',
        content: 'Hello world',
        createdAt: '2026-10-26',
      );
      final cJson = comment.toJson();
      expect(cJson['id'], 50);
      expect(cJson['content'], 'Hello world');

      final att = TaskAttachmentModel(
        id: 77,
        taskId: 10,
        originalName: 'notes.txt',
        fileSize: 1024,
        mimeType: 'text/plain',
        createdAt: '2026-10-26',
      );
      final aJson = att.toJson();
      expect(aJson['id'], 77);
      expect(aJson['original_name'], 'notes.txt');
      expect(aJson['file_size'], 1024);
    });
  });

  group('NotificationModel Deserialization', () {
    test('parses standard NotificationModel correctly', () {
      final json = {
        'id': 15,
        'user_id': 3,
        'workspace_id': 1,
        'type': 'comment_added',
        'data': {
          'title': 'Komentar Baru: Fitur Mobile',
          'message': 'Budi: Desain sudah diperbarui.',
          'task_id': 101,
          'space_slug': 'mobile-app',
        },
        'read_at': null,
        'created_at': '2026-10-26T11:00:00Z',
      };

      final notif = NotificationModel.fromJson(json);

      expect(notif.id, 15);
      expect(notif.userId, 3);
      expect(notif.type, 'comment_added');
      expect(notif.title, 'Komentar Baru: Fitur Mobile');
      expect(notif.message, 'Budi: Desain sudah diperbarui.');
      expect(notif.taskId, 101);
      expect(notif.spaceSlug, 'mobile-app');
      expect(notif.isRead, false);
    });

    test('marks read when read_at is populated', () {
      final json = {
        'id': 16,
        'user_id': 3,
        'title': 'Tugas Selesai',
        'message': 'Semua checklist selesai',
        'read_at': '2026-10-26T12:00:00Z',
        'created_at': '2026-10-26T11:00:00Z',
      };

      final notif = NotificationModel.fromJson(json);
      expect(notif.isRead, true);
    });
  });

  group('Widget Tests: TaskDetailModal & NotificationsSheet', () {
    late KanbanRepository kanbanRepo;

    setUp(() {
      kanbanRepo = MockKanbanRepository();
    });

    testWidgets('renders TaskDetailModal with 2 tabs and initial task data',
        (tester) async {
      tester.view.physicalSize = const Size(1200, 2400);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(() => tester.view.resetPhysicalSize());

      final initialTask = TaskModel(
        id: 99,
        listId: 1,
        stageId: 1,
        title: 'Desain Wireframe Mobile',
        description: 'Tampilan interaktif Kanban dan Task Detail',
        priority: 'urgent',
        startDate: '2026-10-26',
        dueAt: '2026-11-05',
        position: 1,
        stageName: 'Review',
        isDone: false,
        checklists: [
          ChecklistModel(id: 1, title: 'Checklist 1', isCompleted: false),
          ChecklistModel(id: 2, title: 'Checklist 2', isCompleted: true),
        ],
        comments: [
          TaskCommentModel(
            id: 10,
            taskId: 99,
            userId: 5,
            userName: 'Manager',
            content: 'Mohon selesaikan tepat waktu.',
            createdAt: '2026-10-26',
          ),
        ],
        attachments: [
          TaskAttachmentModel(
            id: 20,
            taskId: 99,
            originalName: 'design.png',
            fileSize: 500000,
            mimeType: 'image/png',
            createdAt: '2026-10-26',
          ),
        ],
      );

      final stages = [
        StageModel(
          id: 1,
          listId: 1,
          name: 'Review',
          color: '#00AAAD',
          position: 1,
          isDone: false,
        ),
      ];

      final members = [
        UserModel(id: 5, name: 'Manager', email: 'manager@example.com'),
      ];

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: TaskDetailModal(
              taskId: 99,
              initialTask: initialTask,
              kanbanRepo: kanbanRepo,
              stages: stages,
              spaceMembers: members,
              currentUser: members.first,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify Header & Title
      expect(find.text('Tugas #99'), findsOneWidget);
      expect(find.text('Desain Wireframe Mobile'), findsOneWidget);

      // Verify Tab 1 contents
      expect(find.text('Subtask & Checklist'), findsOneWidget);
      expect(find.text('Checklist 1'), findsOneWidget);
      expect(find.text('Checklist 2'), findsOneWidget);
      expect(find.text('Lampiran Berkas (1)'), findsOneWidget);
      expect(find.text('design.png'), findsOneWidget);

      // Switch to Tab 2: "Diskusi & Komentar"
      await tester.tap(find.textContaining('Diskusi & Komentar'));
      await tester.pumpAndSettle();

      // Verify Tab 2 contents
      expect(find.text('Manager'), findsOneWidget);
      expect(find.text('Mohon selesaikan tepat waktu.'), findsOneWidget);
      expect(find.text('Tulis komentar diskusi...'), findsOneWidget);
    });

    testWidgets('renders NotificationsSheet with header and items',
        (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: NotificationsSheet(
              kanbanRepo: kanbanRepo,
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Verify Notifications Header
      expect(find.text('Notifikasi'), findsOneWidget);
      expect(find.text('Notifikasi Uji'), findsOneWidget);
      expect(find.text('Pesan notifikasi'), findsOneWidget);
    });
  });
}
