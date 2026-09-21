<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CohortController extends Controller
{
    /**
     * Display a listing of all cohorts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('teacher-or-admin');

        $search = trim((string) $request->input('search', ''));

        $query = Cohort::query()
            ->withCount(['users', 'quizForms'])
            ->with('creator:id,name,email')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $cohorts = $query
            ->orderBy('name', 'asc')
            ->paginate(15)
            ->withQueryString();

        $totalCohorts = Cohort::count();
        $totalMembers = \DB::table('cohort_user')->distinct('user_id')->count('user_id');

        $availableClasses = User::query()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas', 'asc')
            ->pluck('kelas');

        return Inertia::render('Cohorts/Index', [
            'cohorts' => $cohorts,
            'filters' => [
                'search' => $search,
            ],
            'stats' => [
                'total_cohorts' => $totalCohorts,
                'total_members' => $totalMembers,
                'total_classes' => $availableClasses->count(),
            ],
            'availableClasses' => $availableClasses,
        ]);
    }

    /**
     * Store a new cohort.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('teacher-or-admin');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:cohorts,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'source_class' => ['nullable', 'string', 'max:50'],
        ]);

        $code = ! empty($validated['code'])
            ? Str::upper(trim($validated['code']))
            : Str::upper(Str::slug($validated['name'], '-'));

        // Ensure unique code if auto-generated
        if (empty($validated['code'])) {
            $baseCode = $code;
            $counter = 1;
            while (Cohort::where('code', $code)->exists()) {
                $code = "{$baseCode}-{$counter}";
                $counter++;
            }
        }

        $cohort = Cohort::create([
            'name' => trim($validated['name']),
            'code' => $code,
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
            'created_by' => Auth::id(),
        ]);

        // Auto-populate from class if requested
        if (! empty($validated['source_class'])) {
            $studentIds = User::query()->students()->where('kelas', $validated['source_class'])->pluck('id');
            if ($studentIds->isNotEmpty()) {
                $cohort->users()->attach($studentIds);
            }
        }

        return back()->with('success', "Cohort '{$cohort->name}' berhasil dibuat.");
    }

    /**
     * Display the specified cohort with members.
     */
    public function show(Request $request, Cohort $cohort): Response
    {
        Gate::authorize('teacher-or-admin');

        $search = trim((string) $request->input('search', ''));
        $selectedClass = trim((string) $request->input('kelas', ''));

        $membersQuery = $cohort->users()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($selectedClass !== '', function ($q) use ($selectedClass): void {
                $q->where('kelas', $selectedClass);
            });

        $members = $membersQuery
            ->orderBy('name', 'asc')
            ->paginate(25)
            ->withQueryString();

        $cohort->loadCount(['users', 'quizForms']);
        $cohort->load('creator:id,name,email');

        // Classes represented in members
        $classes = $cohort->users()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas', 'asc')
            ->pluck('kelas');

        // Available classes from all students (for bulk adding)
        $allAvailableClasses = User::query()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas', 'asc')
            ->pluck('kelas');

        return Inertia::render('Cohorts/Show', [
            'cohort' => $cohort,
            'members' => $members,
            'filters' => [
                'search' => $search,
                'kelas' => $selectedClass,
            ],
            'classes' => $classes,
            'allAvailableClasses' => $allAvailableClasses,
        ]);
    }

    /**
     * Update cohort info.
     */
    public function update(Request $request, Cohort $cohort): RedirectResponse
    {
        Gate::authorize('teacher-or-admin');
        $this->authorizeCohortManagement($request, $cohort);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('cohorts', 'code')->ignore($cohort->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $cohort->update([
            'name' => trim($validated['name']),
            'code' => ! empty($validated['code']) ? Str::upper(trim($validated['code'])) : $cohort->code,
            'description' => ! empty($validated['description']) ? trim($validated['description']) : null,
        ]);

        return back()->with('success', "Data Cohort '{$cohort->name}' berhasil diperbarui.");
    }

    /**
     * Delete cohort.
     */
    public function destroy(Request $request, Cohort $cohort): RedirectResponse
    {
        Gate::authorize('teacher-or-admin');
        $this->authorizeCohortManagement($request, $cohort);

        $name = $cohort->name;
        $cohort->delete();

        return redirect()->route('cohorts.index')->with('success', "Cohort '{$name}' berhasil dihapus.");
    }

    /**
     * Add members to cohort (by user_ids or by class).
     */
    public function addMembers(Request $request, Cohort $cohort): RedirectResponse
    {
        Gate::authorize('teacher-or-admin');
        $this->authorizeCohortManagement($request, $cohort);

        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'class_name' => ['nullable', 'string', 'max:50'],
        ]);

        $idsToAdd = [];

        if (! empty($validated['user_ids'])) {
            $idsToAdd = array_merge($idsToAdd, $validated['user_ids']);
        }

        if (! empty($validated['class_name'])) {
            $classStudentIds = User::query()->students()->where('kelas', $validated['class_name'])->pluck('id')->all();
            $idsToAdd = array_merge($idsToAdd, $classStudentIds);
        }

        $idsToAdd = array_unique($idsToAdd);

        // Ensure only students are attached
        $validStudentIds = User::query()->students()->whereIn('id', $idsToAdd)->pluck('id')->all();

        if (empty($validStudentIds)) {
            return back()->withErrors(['error' => 'Tidak ada anggota murid yang valid untuk ditambahkan.']);
        }

        $cohort->users()->syncWithoutDetaching($validStudentIds);

        $count = count($validStudentIds);

        return back()->with('success', "Berhasil menambahkan {$count} murid ke dalam Cohort '{$cohort->name}'.");
    }

    /**
     * Remove a member from cohort.
     */
    public function removeMember(Request $request, Cohort $cohort, User $user): RedirectResponse
    {
        Gate::authorize('teacher-or-admin');
        $this->authorizeCohortManagement($request, $cohort);

        $cohort->users()->detach($user->id);

        return back()->with('success', "Murid '{$user->name}' berhasil dikeluarkan dari Cohort '{$cohort->name}'.");
    }

    /**
     * Authorize that the user is the creator of the cohort or an administrator.
     */
    protected function authorizeCohortManagement(Request $request, Cohort $cohort): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isAdmin() || $cohort->created_by === $user->id),
            403,
            'Hanya pembuat cohort atau admin yang dapat mengelola cohort ini.'
        );
    }

    /**
     * Automatically sync/create cohorts from existing student classes in 1-click.
     */
    public function syncFromClasses(): RedirectResponse
    {
        Gate::authorize('teacher-or-admin');
        $classes = User::query()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas', 'asc')
            ->pluck('kelas');

        if ($classes->isEmpty()) {
            return back()->withErrors(['error' => 'Belum ada data kelas pada data murid.']);
        }

        $createdCount = 0;
        $attachedTotal = 0;

        foreach ($classes as $className) {
            $code = 'KLS-'.Str::upper(Str::slug($className, '-'));
            $cohort = Cohort::firstOrCreate(
                ['code' => $code],
                [
                    'name' => "Cohort {$className}",
                    'description' => "Cohort otomatis untuk murid {$className}",
                    'created_by' => Auth::id(),
                ]
            );

            if ($cohort->wasRecentlyCreated) {
                $createdCount++;
            }

            $studentIds = User::where('kelas', $className)->pluck('id');
            if ($studentIds->isNotEmpty()) {
                $cohort->users()->syncWithoutDetaching($studentIds);
                $attachedTotal += $studentIds->count();
            }
        }

        return back()->with('success', "Sinkronisasi berhasil: {$createdCount} Cohort baru dibuat dan siswa dikelompokkan sesuai kelas.");
    }
}
