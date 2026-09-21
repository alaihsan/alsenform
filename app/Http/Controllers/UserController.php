<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    /**
     * Display listing of all users (Admin, Guru, Murid) with filters and stats.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $selectedRole = trim((string) $request->input('role', 'all'));
        $selectedClass = trim((string) $request->input('kelas', ''));

        $query = User::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($selectedRole !== 'all' && in_array($selectedRole, ['admin', 'guru', 'siswa'], true), function ($q) use ($selectedRole): void {
                if ($selectedRole === 'admin') {
                    $q->where(function ($sub): void {
                        $sub->where('role', 'admin')->orWhere('is_admin', true);
                    });
                } elseif ($selectedRole === 'guru') {
                    $q->where('role', 'guru')->where('is_admin', false);
                } elseif ($selectedRole === 'siswa') {
                    $q->where(function ($sub): void {
                        $sub->where('role', 'siswa')->orWhereNotNull('nis');
                    });
                }
            })
            ->when($selectedClass !== '', function ($q) use ($selectedClass): void {
                $q->where('kelas', $selectedClass);
            });

        $users = $query
            ->orderByRaw("CASE WHEN role = 'admin' OR is_admin = 1 THEN 1 WHEN role = 'guru' THEN 2 ELSE 3 END")
            ->orderByRaw('kelas IS NULL, kelas ASC')
            ->orderBy('name', 'asc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'nis' => $u->nis,
                'kelas' => $u->kelas,
                'role' => $u->is_admin ? 'admin' : ($u->role ?: ($u->nis ? 'siswa' : 'guru')),
                'is_admin' => (bool) $u->is_admin,
                'default_password' => $u->nis ? User::defaultPasswordForNis($u->nis) : '',
                'created_at' => $u->created_at?->format('d M Y H:i'),
            ]);

        $totalUsers = User::count();
        $totalAdmins = User::where('role', 'admin')->orWhere('is_admin', true)->count();
        $totalTeachers = User::where('role', 'guru')->where('is_admin', false)->count();
        $totalStudents = User::where('role', 'siswa')->orWhereNotNull('nis')->count();

        $classes = User::whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas', 'asc')
            ->pluck('kelas');

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $selectedRole,
                'kelas' => $selectedClass,
            ],
            'classes' => $classes,
            'stats' => [
                'total_users' => $totalUsers,
                'total_admins' => $totalAdmins,
                'total_teachers' => $totalTeachers,
                'total_students' => $totalStudents,
                'total_classes' => $classes->count(),
            ],
            // Backward compatibility for students view props
            'students' => $users,
        ]);
    }

    /**
     * Store a new user (Admin, Guru, or Murid).
     */
    public function store(Request $request): RedirectResponse
    {
        $role = $request->input('role', 'siswa');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(['admin', 'guru', 'siswa'])],
        ];

        if ($role === 'siswa') {
            $rules['nis'] = ['required', 'string', 'max:50', 'unique:users,nis'];
            $rules['kelas'] = ['nullable', 'string', 'max:50'];
            $rules['email'] = ['nullable', 'string', 'email', 'max:255', 'unique:users,email'];
        } else {
            $rules['email'] = ['required', 'string', 'email', 'max:255', 'unique:users,email'];
            $rules['password'] = ['required', 'string', 'min:6'];
        }

        $validated = $request->validate($rules);

        $isAdmin = $role === 'admin';

        if ($role === 'siswa') {
            $nis = trim($validated['nis']);
            $password = Hash::make(User::defaultPasswordForNis($nis));
            $email = ! empty($validated['email']) ? $validated['email'] : null;
            $kelas = ! empty($validated['kelas']) ? trim($validated['kelas']) : null;
        } else {
            $nis = null;
            $kelas = null;
            $email = trim($validated['email']);
            $password = Hash::make($validated['password']);
        }

        User::create([
            'name' => trim($validated['name']),
            'email' => $email,
            'nis' => $nis,
            'kelas' => $kelas,
            'password' => $password,
            'role' => $role,
            'is_admin' => $isAdmin,
        ]);

        $roleLabel = match ($role) {
            'admin' => 'Administrator',
            'guru' => 'Guru',
            default => 'Murid',
        };

        return back()->with('success', "Akun {$roleLabel} '{$validated['name']}' berhasil ditambahkan.");
    }

    /**
     * Update user details.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $role = $request->input('role', $user->role ?: 'siswa');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', Rule::in(['admin', 'guru', 'siswa'])],
            'nis' => ['nullable', 'string', 'max:50', Rule::unique('users', 'nis')->ignore($user->id)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'kelas' => ['nullable', 'string', 'max:50'],
        ];

        if ($role !== 'siswa') {
            $rules['email'] = ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        $validated = $request->validate($rules);

        $isAdmin = $role === 'admin';

        $user->update([
            'name' => trim($validated['name']),
            'email' => ! empty($validated['email']) ? trim($validated['email']) : null,
            'nis' => $role === 'siswa' && ! empty($validated['nis']) ? trim($validated['nis']) : ($role === 'siswa' ? $user->nis : null),
            'kelas' => $role === 'siswa' && ! empty($validated['kelas']) ? trim($validated['kelas']) : null,
            'role' => $role,
            'is_admin' => $isAdmin,
        ]);

        return back()->with('success', "Data pengguna '{$user->name}' berhasil diperbarui.");
    }

    /**
     * Change user role (Admin, Guru, Murid) directly by superadmin.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['admin', 'guru', 'siswa'])],
        ]);

        $newRole = $validated['role'];
        $isAdmin = $newRole === 'admin';

        // Do not demote yourself if you are the logged in admin
        if ($user->id === Auth::id() && ! $isAdmin) {
            return back()->withErrors(['error' => 'Anda tidak dapat mencabut peran admin dari akun Anda sendiri.']);
        }

        $user->update([
            'role' => $newRole,
            'is_admin' => $isAdmin,
        ]);

        $roleLabel = match ($newRole) {
            'admin' => 'Administrator',
            'guru' => 'Guru',
            default => 'Murid',
        };

        return back()->with('success', "Peran '{$user->name}' berhasil diubah menjadi {$roleLabel}.");
    }

    /**
     * Change user password directly by admin.
     */
    public function changePassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password '{$user->name}' berhasil diubah.");
    }

    /**
     * Reset student password to default (last 6 digits of NIS).
     */
    public function resetPassword(User $user): RedirectResponse
    {
        if (empty($user->nis)) {
            return back()->withErrors(['error' => 'Pengguna tidak memiliki NIS untuk membuat password default.']);
        }

        $defaultPassword = User::defaultPasswordForNis($user->nis);
        $user->update([
            'password' => Hash::make($defaultPassword),
        ]);

        return back()->with('success', "Password '{$user->name}' berhasil direset ke default 6 digit NIS: {$defaultPassword}.");
    }

    /**
     * Delete user.
     */
    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->withErrors(['error' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "Pengguna '{$name}' berhasil dihapus.");
    }

    /**
     * Preview or execute student import.
     */
    public function importStudents(Request $request, StudentImportService $service): JsonResponse|RedirectResponse
    {
        if ($request->has('students') && is_array($request->input('students'))) {
            $students = $request->input('students');
            $result = $service->import($students);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Berhasil mengimpor {$result['created']} murid baru dan memperbarui {$result['updated']} murid.",
                    'data' => $result,
                ]);
            }

            return back()->with('success', "Berhasil mengimpor {$result['created']} murid baru dan memperbarui {$result['updated']} murid.");
        }

        $request->validate([
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'text' => ['nullable', 'string', 'max:500000'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run', false);

        if ($request->hasFile('file')) {
            $parsed = $service->parseFile($request->file('file'));
        } elseif ($request->filled('text')) {
            $parsed = $service->parseContent($request->input('text'));
        } else {
            return response()->json([
                'message' => 'Silakan unggah file CSV atau tempel (paste) data murid.',
            ], 422);
        }

        if ($dryRun || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'preview' => $parsed['valid'],
                'errors' => $parsed['errors'],
                'total_rows' => $parsed['total_rows'],
                'valid_count' => count($parsed['valid']),
                'error_count' => count($parsed['errors']),
            ]);
        }

        if (empty($parsed['valid'])) {
            return back()->withErrors(['error' => 'Tidak ada baris data murid yang valid untuk diimpor.']);
        }

        $result = $service->import($parsed['valid']);

        return back()->with('success', "Berhasil mengimpor {$result['created']} murid baru dan memperbarui {$result['updated']} murid.");
    }

    /**
     * Download CSV template for student import.
     */
    public function downloadStudentTemplate(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_impor_murid.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $sampleData = [
            ['NIS', 'NAMA', 'KELAS'],
            ['202401001', 'Ahmad Fauzi', 'Kelas 7A'],
            ['202401002', 'Siti Nurhaliza', 'Kelas 7A'],
            ['202401003', 'Budi Santoso', 'Kelas 8B'],
            ['202401004', 'Dewi Lestari', 'Kelas 8B'],
            ['202401005', 'Reza Rahadian', 'Kelas 9C'],
        ];

        return response()->stream(function () use ($sampleData): void {
            $file = fopen('php://output', 'w');
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        }, 200, $headers);
    }
}
