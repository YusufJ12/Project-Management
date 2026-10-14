import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'core/constants/api_endpoints.dart';
import 'core/network/api_client.dart';
import 'features/kanban/models/stage_model.dart';
import 'features/kanban/repositories/kanban_repository.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const NexSphereApp());
}

class NexSphereApp extends StatelessWidget {
  const NexSphereApp({super.key});

  @override
  Widget build(BuildContext context) {
    final apiClient = ApiClient();
    final kanbanRepo = KanbanRepository(apiClient);

    return MaterialApp(
      title: 'NexSphere Project Management',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF00AAAD),
          brightness: Brightness.light,
        ),
        scaffoldBackgroundColor: const Color(0xFFF8FAFC),
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.white,
          foregroundColor: Color(0xFF0F172A),
          elevation: 0,
          scrolledUnderElevation: 1,
        ),
      ),
      darkTheme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF00AAAD),
          brightness: Brightness.dark,
        ),
        scaffoldBackgroundColor: const Color(0xFF0F172A),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF1E293B),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
      ),
      themeMode: ThemeMode.system,
      home: SplashScreen(apiClient: apiClient, kanbanRepo: kanbanRepo),
    );
  }
}

// ==========================================
// 1. SPLASH SCREEN: Auth check
// ==========================================
class SplashScreen extends StatefulWidget {
  final ApiClient apiClient;
  final KanbanRepository kanbanRepo;

  const SplashScreen({super.key, required this.apiClient, required this.kanbanRepo});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    await Future.delayed(const Duration(milliseconds: 600));
    final hasToken = await widget.apiClient.hasToken();

    if (!mounted) return;

    if (hasToken) {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => WorkspaceScreen(
            apiClient: widget.apiClient,
            kanbanRepo: widget.kanbanRepo,
          ),
        ),
      );
    } else {
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => LoginScreen(
            apiClient: widget.apiClient,
            kanbanRepo: widget.kanbanRepo,
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.hub_rounded, size: 64, color: Color(0xFF00AAAD)),
            SizedBox(height: 16),
            Text(
              'NexSphere',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold, letterSpacing: -0.5),
            ),
            SizedBox(height: 8),
            Text('Project Management Mobile', style: TextStyle(color: Colors.grey)),
            SizedBox(height: 24),
            CircularProgressIndicator(strokeWidth: 2),
          ],
        ),
      ),
    );
  }
}

// ==========================================
// 2. LOGIN SCREEN
// ==========================================
class LoginScreen extends StatefulWidget {
  final ApiClient apiClient;
  final KanbanRepository kanbanRepo;

  const LoginScreen({super.key, required this.apiClient, required this.kanbanRepo});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailCtrl = TextEditingController(text: 'admin@nexsphere.test');
  final _passCtrl = TextEditingController(text: 'admin123');
  bool _isLoading = false;
  String? _errorMessage;

  Future<void> _login() async {
    final email = _emailCtrl.text.trim();
    final pass = _passCtrl.text;
    if (email.isEmpty || pass.isEmpty) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final res = await widget.apiClient.dio.post(
        ApiEndpoints.login,
        data: {'email': email, 'password': pass},
      );

      final token = res.data['token'] as String;
      await widget.apiClient.saveToken(token);

      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => WorkspaceScreen(
            apiClient: widget.apiClient,
            kanbanRepo: widget.kanbanRepo,
          ),
        ),
      );
    } on DioException catch (e) {
      setState(() {
        _errorMessage = e.response?.data?['message'] ?? 'Gagal login. Periksa email & kata sandi.';
      });
    } catch (e) {
      setState(() {
        _errorMessage = 'Terjadi kesalahan koneksi server.';
      });
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(28.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(Icons.hub_rounded, size: 56, color: Color(0xFF00AAAD)),
                const SizedBox(height: 16),
                const Text(
                  'Masuk ke NexSphere',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 6),
                const Text(
                  'Kelola tugas & kolaborasi proyek tim dari mana saja.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.grey, fontSize: 13),
                ),
                const SizedBox(height: 32),
                if (_errorMessage != null)
                  Container(
                    padding: const EdgeInsets.all(12),
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: Colors.red.withAlpha(25),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.red.withAlpha(80)),
                    ),
                    child: Text(_errorMessage!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                  ),
                TextField(
                  controller: _emailCtrl,
                  keyboardType: TextInputType.emailAddress,
                  decoration: InputDecoration(
                    labelText: 'Email / Username',
                    prefixIcon: const Icon(Icons.alternate_email_rounded, size: 20),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _passCtrl,
                  obscureText: true,
                  decoration: InputDecoration(
                    labelText: 'Kata Sandi',
                    prefixIcon: const Icon(Icons.lock_outline_rounded, size: 20),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: _isLoading ? null : _login,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF00AAAD),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isLoading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Masuk Sekarang', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
                const SizedBox(height: 20),
                TextButton.icon(
                  onPressed: () => _showServerSettingsDialog(context),
                  icon: const Icon(Icons.settings_outlined, size: 16),
                  label: const Text('Pengaturan Alamat Server API', style: TextStyle(fontSize: 12)),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _showServerSettingsDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Alamat Server API'),
        content: const Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Base URL API saat ini:',
              style: TextStyle(fontSize: 12, color: Colors.grey),
            ),
            SizedBox(height: 6),
            SelectableText(
              ApiEndpoints.baseUrl,
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
            ),
            SizedBox(height: 12),
            Text(
              'Aplikasi ini terhubung langsung ke server live:\nhttps://projectmanagement.nexsphere.web.id/api/v1',
              style: TextStyle(fontSize: 11, color: Colors.grey),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Tutup')),
        ],
      ),
    );
  }
}

// ==========================================
// 3. WORKSPACE & SPACES LIST SCREEN
// ==========================================
class WorkspaceScreen extends StatefulWidget {
  final ApiClient apiClient;
  final KanbanRepository kanbanRepo;

  const WorkspaceScreen({super.key, required this.apiClient, required this.kanbanRepo});

  @override
  State<WorkspaceScreen> createState() => _WorkspaceScreenState();
}

class _WorkspaceScreenState extends State<WorkspaceScreen> {
  bool _isLoading = true;
  List<dynamic> _workspaces = [];
  Map<String, dynamic>? _selectedWorkspace;
  List<dynamic> _spaces = [];
  String? _userName;

  @override
  void initState() {
    super.initState();
    _loadWorkspaces();
  }

  Future<void> _loadWorkspaces() async {
    setState(() => _isLoading = true);
    try {
      final meRes = await widget.apiClient.dio.get(ApiEndpoints.me);
      _userName = meRes.data['user']?['name'];

      final wsRes = await widget.apiClient.dio.get(ApiEndpoints.workspaces);
      final wsList = wsRes.data['data'] as List<dynamic>? ?? [];

      if (mounted) {
        setState(() {
          _workspaces = wsList;
          if (wsList.isNotEmpty) {
            _selectedWorkspace = wsList.first as Map<String, dynamic>;
          }
        });
        if (_selectedWorkspace != null) {
          await _loadSpacesForWorkspace(_selectedWorkspace!['id'] as int);
        }
      }
    } catch (e) {
      // Handle error
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _loadSpacesForWorkspace(int workspaceId) async {
    try {
      final res = await widget.apiClient.dio.get(ApiEndpoints.workspaceSpaces(workspaceId));
      if (mounted) {
        setState(() {
          _spaces = res.data['data'] as List<dynamic>? ?? [];
        });
      }
    } catch (e) {
      // Handle error
    }
  }

  Future<void> _logout() async {
    await widget.apiClient.clearToken();
    if (!mounted) return;
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => LoginScreen(
          apiClient: widget.apiClient,
          kanbanRepo: widget.kanbanRepo,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('NexSphere Workspaces', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            if (_userName != null)
              Text('Halo, $_userName', style: const TextStyle(fontSize: 11, color: Colors.grey)),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded, size: 20),
            tooltip: 'Keluar',
            onPressed: _logout,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadWorkspaces,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_workspaces.length > 1) ...[
                    const Text('Pilih Workspace:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.grey)),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedWorkspace?['id'] as int?,
                      decoration: InputDecoration(
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      ),
                      items: _workspaces.map((w) {
                        return DropdownMenuItem<int>(
                          value: w['id'] as int,
                          child: Text(w['name'] as String, style: const TextStyle(fontWeight: FontWeight.w600)),
                        );
                      }).toList(),
                      onChanged: (id) {
                        if (id != null) {
                          final selected = _workspaces.firstWhere((w) => w['id'] == id);
                          setState(() => _selectedWorkspace = selected);
                          _loadSpacesForWorkspace(id);
                        }
                      },
                    ),
                    const SizedBox(height: 20),
                  ],
                  const Text('Daftar Space Proyek:', style: TextStyle(fontSize: 14, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 10),
                  if (_spaces.isEmpty)
                    Container(
                      padding: const EdgeInsets.all(24),
                      alignment: Alignment.center,
                      child: const Column(
                        children: [
                          Icon(Icons.folder_open_rounded, size: 48, color: Colors.grey),
                          SizedBox(height: 8),
                          Text('Belum ada Space proyek di workspace ini.', style: TextStyle(color: Colors.grey, fontSize: 13)),
                        ],
                      ),
                    )
                  else
                    ..._spaces.map((sp) {
                      return Card(
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                          side: BorderSide(color: Colors.grey.withAlpha(40)),
                        ),
                        margin: const EdgeInsets.only(bottom: 12),
                        child: InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => KanbanBoardScreen(
                                  spaceId: sp['id'] as int,
                                  spaceName: sp['name'] as String,
                                  kanbanRepo: widget.kanbanRepo,
                                ),
                              ),
                            );
                          },
                          child: Padding(
                            padding: const EdgeInsets.all(16),
                            child: Row(
                              children: [
                                Container(
                                  width: 42,
                                  height: 42,
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF00AAAD).withAlpha(30),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: const Icon(Icons.view_kanban_rounded, color: Color(0xFF00AAAD), size: 24),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        sp['name'] as String,
                                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        sp['description'] as String? ?? 'Space proyek tim',
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(color: Colors.grey, fontSize: 12),
                                      ),
                                    ],
                                  ),
                                ),
                                const Icon(Icons.chevron_right_rounded, color: Colors.grey),
                              ],
                            ),
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }
}

// ==========================================
// 4. KANBAN BOARD SCREEN
// ==========================================
class KanbanBoardScreen extends StatefulWidget {
  final int spaceId;
  final String spaceName;
  final KanbanRepository kanbanRepo;

  const KanbanBoardScreen({
    super.key,
    required this.spaceId,
    required this.spaceName,
    required this.kanbanRepo,
  });

  @override
  State<KanbanBoardScreen> createState() => _KanbanBoardScreenState();
}

class _KanbanBoardScreenState extends State<KanbanBoardScreen> {
  bool _isLoading = true;
  List<StageModel> _stages = [];

  @override
  void initState() {
    super.initState();
    _fetchStages();
  }

  Future<void> _fetchStages() async {
    setState(() => _isLoading = true);
    try {
      final stages = await widget.kanbanRepo.getSpaceStages(widget.spaceId);
      if (mounted) {
        setState(() {
          _stages = stages;
        });
      }
    } catch (e) {
      // Handle error
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Color _parseColor(String? hexString) {
    if (hexString == null || hexString.isEmpty) return const Color(0xFF64748B);
    try {
      final buffer = StringBuffer();
      if (hexString.length == 6 || hexString.length == 7) buffer.write('ff');
      buffer.write(hexString.replaceFirst('#', ''));
      return Color(int.parse(buffer.toString(), radix: 16));
    } catch (_) {
      return const Color(0xFF64748B);
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.spaceName, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Muat ulang',
            onPressed: _fetchStages,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _stages.isEmpty
              ? const Center(child: Text('Tidak ada tahapan kolom di Space ini.'))
              : ListView.builder(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                  itemCount: _stages.length,
                  itemBuilder: (context, index) {
                    final stage = _stages[index];
                    final stageColor = _parseColor(stage.color);

                    return Container(
                      width: 280,
                      margin: const EdgeInsets.only(right: 12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).cardColor,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.grey.withAlpha(35)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          // Column Header
                          Padding(
                            padding: const EdgeInsets.all(14.0),
                            child: Row(
                              children: [
                                Container(
                                  width: 10,
                                  height: 10,
                                  decoration: BoxDecoration(color: stageColor, shape: BoxShape.circle),
                                ),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: Text(
                                    stage.name,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: Colors.grey.withAlpha(30),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Text(
                                    '${stage.tasks.length}',
                                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Divider(height: 1),
                          // Task Cards List
                          Expanded(
                            child: stage.tasks.isEmpty
                                ? Center(
                                    child: Text(
                                      'Belum ada tugas',
                                      style: TextStyle(fontSize: 12, color: Colors.grey.withAlpha(150)),
                                    ),
                                  )
                                : ListView.builder(
                                    padding: const EdgeInsets.all(10),
                                    itemCount: stage.tasks.length,
                                    itemBuilder: (ctx, tIdx) {
                                      final task = stage.tasks[tIdx];
                                      return Card(
                                        elevation: 0.5,
                                        margin: const EdgeInsets.only(bottom: 8),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(12),
                                          side: BorderSide(color: Colors.grey.withAlpha(30)),
                                        ),
                                        child: Padding(
                                          padding: const EdgeInsets.all(12),
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                children: [
                                                  Container(
                                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                    decoration: BoxDecoration(
                                                      color: _priorityColor(task.priority).withAlpha(25),
                                                      borderRadius: BorderRadius.circular(6),
                                                    ),
                                                    child: Text(
                                                      task.priority.toUpperCase(),
                                                      style: TextStyle(
                                                        fontSize: 9,
                                                        fontWeight: FontWeight.bold,
                                                        color: _priorityColor(task.priority),
                                                      ),
                                                    ),
                                                  ),
                                                ],
                                              ),
                                              const SizedBox(height: 6),
                                              Text(
                                                task.title,
                                                style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                                              ),
                                              if (task.dueAt != null || task.checklistsCount > 0) ...[
                                                const SizedBox(height: 8),
                                                Row(
                                                  children: [
                                                    if (task.checklistsCount > 0) ...[
                                                      Icon(Icons.check_box_outlined, size: 14, color: Colors.grey.shade600),
                                                      const SizedBox(width: 4),
                                                      Text(
                                                        '${task.completedChecklistsCount}/${task.checklistsCount}',
                                                        style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                                      ),
                                                      const SizedBox(width: 10),
                                                    ],
                                                    if (task.dueAt != null) ...[
                                                      Icon(Icons.calendar_today_outlined, size: 13, color: Colors.grey.shade600),
                                                      const SizedBox(width: 4),
                                                      Text(
                                                        task.dueAt!,
                                                        style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                                                      ),
                                                    ],
                                                  ],
                                                ),
                                              ],
                                            ],
                                          ),
                                        ),
                                      );
                                    },
                                  ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
    );
  }
}
