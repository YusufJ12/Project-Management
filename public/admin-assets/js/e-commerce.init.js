@extends('partials.layouts.master')

@section('title', $project->name . ' – Project Detail')
@section('pagetitle', 'Project Detail')

@section('css')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />
    <style>
        /* ── Project Header ── */
        .project-detail-header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 2rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
        }

        .project-detail-header h2 {
            font-weight: 700;
            margin: 0;
        }

        .project-detail-header .breadcrumb {
            background: rgba(255, 255, 255, .08);
            border-radius: 8px;
            padding: .5rem 1rem;
            margin-top: .75rem;
            margin-bottom: 0;
        }

        .project-detail-header .breadcrumb-item a {
            color: rgba(255, 255, 255, .85);
            text-decoration: none;
        }

        .project-detail-header .breadcrumb-item.active,
        .project-detail-header .breadcrumb-item+.breadcrumb-item::before {
            color: white;
        }

        /* ── Progress bar ── */
        .progress-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
        }

        .progress-bar-label {
            font-size: .75rem;
            font-weight: 600;
        }

        /* ── Sprint card ── */
        .sprint-card {
            border-radius: 12px;
            border: 1px solid #e3e6f0;
            margin-bottom: 1rem;
            transition: box-shadow .2s;
        }

        .sprint-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, .10);
        }

        .sprint-card .card-header {
            border-radius: 12px 12px 0 0;
            padding: .75rem 1.25rem;
        }

        .sprint-status-active {
            background: rgba(25, 135, 84, .12);
            border-left: 4px solid #198754;
        }

        .sprint-status-planned {
            background: rgba(13, 110, 253, .08);
            border-left: 4px solid #00aaad;
        }

        .sprint-status-completed {
            background: rgba(108, 117, 125, .1);
            border-left: 4px solid #6c757d;
        }

        /* ── Stat cards ── */
        .stat-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .07);
            text-align: center;
            padding: 1.2rem .5rem;
        }

        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
        }

        /* ── Section ── */
        .section-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
    </style>
@endsection

@section('content')
    <div class="container-fluid">

        {{-- ── Header ── --}}
        <div class="project-detail-header d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2><i class="bi bi-folder2-open me-2"></i>{{ $project->name }}</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
                        <li class="breadcrumb-item active">{{ Str::limit($project->name, 30) }}</li>
                    </ol>
                </nav>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('tasks.kanban') }}?project_id={{ $project->id }}" class="btn btn-light btn-sm">
                    <i class="bi bi-kanban me-1"></i>Kanban Board
                </a>
                <a href="{{ route('tasks.index') }}?project_id={{ $project->id }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-list-task me-1"></i>Task List
                </a>
                <a href="{{ route('projects.index') }}" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>Back to Projects
                </a>
            </div>
        </div>

        {{-- ── Row: Info + Progress ── --}}
        <div class="row g-3 mb-4">

            {{-- Project Info --}}
            <div class="col-lg-8">
                <div class="card progress-card h-100">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <small class="text-muted">Status</small>
                                <div class="mt-1">{!! $project->status_badge !!}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Department</small>
                                <div class="fw-semibold mt-1">{{ $project->department->name ?? '–' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Start Date</small>
                                <div class="fw-semibold mt-1">
                                    {{ $project->start_date ? $project->start_date->format('d M Y') : '–' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">End Date</small>
                                <div class="fw-semibold mt-1">
                                    {{ $project->end_date ? $project->end_date->format('d M Y') : '–' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Budget</small>
                                <div class="fw-semibold mt-1">{{ $project->formatted_budget }}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Created By</small>
                                <div class="fw-semibold mt-1">{{ $project->creator->name ?? '–' }}</div>
                            </div>
                            @if ($project->description)
                                <div class="col-12">
                                    <small class="text-muted">Description</small>
                                    <div class="mt-1">{{ $project->description }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Task Stats + Progress ── --}}
            <div class="col-lg-4">
                <div class="card progress-card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="progress-bar-label text-muted">Overall Progress</span>
                            <span class="fw-bold text-primary" id="progressPercent">{{ $project->progress ?? 0 }}%</span>
                        </div>
                        <div class="progress" style="height:12px; border-radius:8px;">
                            <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated"
                                role="progressbar" style="width: {{ $project->progress ?? 0 }}%;">
                            </div>
                        </div>
                        <small class="text-muted">{{ $doneTasks }} / {{ $totalTasks }} tasks completed</small>
                    </div>
                </div>

                {{-- Active Sprint badge --}}
                <div class="card progress-card">
                    <div class="card-body py-3">
                        <small class="text-muted d-block mb-1">Active Sprint</small>
                        @if ($activeSprint)
                            <span class="badge bg-success fs-6">{{ $activeSprint->name }}</span>
                            <div class="text-muted small mt-1">
                                {{ $activeSprint->start_date ? $activeSprint->start_date->format('d M') : '' }}
                                –
                                {{ $activeSprint->end_date ? $activeSprint->end_date->format('d M Y') : '' }}
                            </div>
                            @if ($activeSprint->goal)
                                <div class="mt-1 text-muted small"><i
                                        class="bi bi-bullseye me-1"></i>{{ $activeSprint->goal }}</div>
                            @endif
                        @else
                            <span class="text-muted">No active sprint</span>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        {{-- ── Sprints Section ── --}}
        <div class="row g-3">

            <div class="col-lg-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="section-title"><i class="bi bi-lightning-charge-fill text-warning"></i> Sprints</div>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sprintModal"
                        onclick="openCreateSprintModal()">
                        <i class="bi bi-plus-circle me-1"></i>New Sprint
                    </button>
                </div>

                <div id="sprintList">
                    @forelse ($project->sprints as $sprint)
                        @include('projectmanagement::projects.partials.sprint-card', ['sprint' => $sprint])
                    @empty
                        <div class="text-center py-5 text-muted" id="noSprintsMsg">
                            <i class="bi bi-lightning-charge display-4 d-block mb-3 opacity-25"></i>
                            <p>No sprints yet. Create a sprint to start planning your work.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

    </div>

    {{-- ── Sprint Create/Edit Modal ── --}}
    <div class="modal fade" id="sprintModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="sprintModalTitle"><i class="bi bi-lightning-charge me-2"></i>New Sprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="sprintForm">
                    @csrf
                    <input type="hidden" id="sprint_id" name="sprint_id">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-bold">Sprint Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="sprint_name" name="name" required
                                    placeholder="e.g. Sprint 1">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold">Sprint Goal</label>
                                <textarea class="form-control" id="sprint_goal" name="goal" rows="2"
                                    placeholder="What do we want to achieve in this sprint?"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="sprint_start_date" name="start_date"
                                    required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="sprint_end_date" name="end_date"
                                    required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="sprintSubmitBtn">
                            <i class="bi bi-save me-1"></i>Save Sprint
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        const PROJECT_ID = {{ $project->id }};
        const CSRF_TOKEN = '{{ csrf_token() }}';

        /* ──────────────────────────────────────────────
           Sprint Modal helpers
        ────────────────────────────────────────────── */
        function openCreateSprintModal() {
            document.getElementById('sprintModalTitle').textContent = '✚ New Sprint';
            document.getElementById('sprint_id').value = '';
            document.getElementById('sprint_name').value = '';
            document.getElementById('sprint_goal').value = '';
            document.getElementById('sprint_start_date').value = '';
            document.getElementById('sprint_end_date').value = '';
        }

        function editSprint(sprintId) {
            fetch(`/sprints/${sprintId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    }
                })
                .then(r => r.json())
                .then(data => {
                    document.getElementById('sprintModalTitle').textContent = '✎ Edit Sprint';
                    document.getElementById('sprint_id').value = data.id;
                    document.getElementById('sprint_name').value = data.name;
                    document.getElementById('sprint_goal').value = data.goal ?? '';
                    document.getElementById('sprint_start_date').value = data.start_date ?? '';
                    document.getElementById('sprint_end_date').value = data.end_date ?? '';

                    const modal = new bootstrap.Modal(document.getElementById('sprintModal'));
                    modal.show();
                })
                .catch(() => toastr.error('Failed to load sprint data.'));
        }

        /* ──────────────────────────────────────────────
           Submit sprint form
        ────────────────────────────────────────────── */
        document.getElementById('sprintForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const sprintId = document.getElementById('sprint_id').value;
            const isEdit = !!sprintId;

            const url = isEdit ? `/sprints/${sprintId}` : `/projects/${PROJECT_ID}/sprints`;
            const method = isEdit ? 'PUT' : 'POST';

            const body = {
                name: document.getElementById('sprint_name').value,
                goal: document.getElementById('sprint_goal').value,
                start_date: document.getElementById('sprint_start_date').value,
                end_date: document.getElementById('sprint_end_date').value,
                _token: CSRF_TOKEN,
            };

            if (isEdit) body._method = 'PUT';

            fetch(url, {
                    method: 'POST', // Always POST; _method spoofing for PUT
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        ...body
                    })
                })
                .then(async r => {
                    const data = await r.json();
                    if (!r.ok || !data.success) throw new Error(data.message ?? 'Error');
                    bootstrap.Modal.getInstance(document.getElementById('sprintModal')).hide();
                    toastr.success(data.message);
                    setTimeout(() => location.reload(), 900);
                })
                .catch(err => toastr.error(err.message));
        });

        /* Actually use the right method for PUT */
        document.getElementById('sprintForm').addEventListener('submit', function() {}, false);

        /* ──────────────────────────────────────────────
           Sprint Actions: activate / complete / delete
        ────────────────────────────────────────────── */
        function sprintAction(sprintId, action) {
            const labels = {
                activate: 'Activate',
                complete: 'Mark as Completed',
                destroy: 'Delete'
            };
            if (!confirm(`${labels[action] ?? action} this sprint?`)) return;

            let url, method;
            if (action === 'activate') {
                url = `/sprints/${sprintId}/activate`;
                method = 'PATCH';
            } else if (action === 'complete') {
                url = `/sprints/${sprintId}/complete`;
                method = 'PATCH';
            } else {
                url = `/sprints/${sprintId}`;
                method = 'DELETE';
            }

            fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        _method: method,
                        _token: CSRF_TOKEN
                    })
                })
                .then(async r => {
                    const data = await r.json();
                    if (!r.ok || !data.success) throw new Error(data.message ?? 'Error');
                    toastr.success(data.message);
                    setTimeout(() => location.reload(), 900);
                })
                .catch(err => toastr.error(err.message));
        }
    </script>
@endsection
