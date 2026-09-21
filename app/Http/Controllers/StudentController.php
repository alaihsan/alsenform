<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    /**
     * Display a listing of students with stats, filters, and pagination.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $selectedClass = trim((string) $request->input('kelas', ''));

        $query = User::query()
            ->students()
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

        $students = $query
            ->orderByRaw('kelas IS NULL, kelas ASC')
            ->orderBy('nis', 'asc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $student): array => [
                'id' => $student->id,
                'nis' => $student->nis,
                'name' => $student->name,
                'kelas' => $student->kelas,
                'email' => $student->email,
                'default_password' => $student->nis ? User::defaultPasswordForNis($student->nis) : '',
                'created_at' => $student->created_at?->format('d M Y H:i'),
            ]);

        $totalStudents = User::query()->students()->count();
        $classes = User::query()
            ->students()
            ->whereNotNull('kelas')
            ->where('kelas', '!=', '')
            ->distinct()
            ->orderBy('kelas', 'asc')
            ->pluck('kelas');

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => [
                'search' => $search,
                'kelas' => $selectedClass,
            ],
            'classes' => $classes,
            'stats' => [
                'total_students' => $totalStudents,
                'total_classes' => $classes->count(),
            ],
        ]);
    }

    /**
     * Store a single student.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nis' => ['required', 'string', 'max:50', 'unique:users,nis'],
            'name' => ['required', 'string', 'max:255'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
        ]);

        $nis = trim($validated['nis']);
        $defaultPassword = User::defaultPasswordForNis($nis);
        $email = ! empty($validated['email']) ? $validated['email'] : null;

        User::create([
            'name' => trim($validated['name']),
            'nis' => $nis,
            'kelas' => ! empty($validated['kelas']) ? trim($validated['kelas']) : null,
            'email' => $email,
            'password' => Hash::make($defaultPassword),
            'role' => 'siswa',
            'is_admin' => false,
        ]);

        return back()->with('success', "Murid '{$validated['name']}' berhasil ditambahkan dengan password default 6 digit NIS: {$defaultPassword}.");
    }

    /**
     * Update student details.
     */
    public function update(Request $request, User $student): RedirectResponse
    {
        $validated = $request->validate([
            'nis' => ['required', 'string', 'max:50', Rule::unique('users', 'nis')->ignore($student->id)],
            'name' => ['required', 'string', 'max:255'],
            'kelas' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->id)],
        ]);

        $nis = trim($validated['nis']);
        $email = ! empty($validated['email']) ? $validated['email'] : null;

        $student->update([
            'name' => trim($validated['name']),
            'nis' => $nis,
            'kelas' => ! empty($validated['kelas']) ? trim($validated['kelas']) : null,
            'email' => $email,
        ]);

        return back()->with('success', "Data murid '{$student->name}' berhasil diperbarui.");
    }

    /**
     * Delete student.
     */
    public function destroy(User $student): RedirectResponse
    {
        $name = $student->name;
        $student->delete();

        return back()->with('success', "Murid '{$name}' berhasil dihapus.");
    }

    /**
     * Reset student password to default (last 6 digits of NIS).
     */
    public function resetPassword(User $student): RedirectResponse
    {
        if (empty($student->nis)) {
            return back()->withErrors(['error' => 'Murid tidak memiliki NIS untuk membuat password default.']);
        }

        $defaultPassword = User::defaultPasswordForNis($student->nis);
        $student->update([
            'password' => Hash::make($defaultPassword),
        ]);

        return back()->with('success', "Password murid '{$student->name}' berhasil direset ke password default: {$defaultPassword}.");
    }

    /**
     * Change student password directly by admin.
     */
    public function changePassword(Request $request, User $student): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:6'],
        ]);

        $student->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', "Password murid '{$student->name}' berhasil diubah.");
    }

    /**
     * Preview or execute student import.
     */
    public function import(Request $request, StudentImportService $service): JsonResponse|RedirectResponse
    {
        // Check if pre-parsed students array was submitted directly
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

        // Otherwise parse file or text
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

        // If dry run / preview requested, return parsed data
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

        // Direct execution
        if (empty($parsed['valid'])) {
            return back()->withErrors(['error' => 'Tidak ada baris data murid yang valid untuk diimpor.']);
        }

        $result = $service->import($parsed['valid']);

        return back()->with('success', "Berhasil mengimpor {$result['created']} murid baru dan memperbarui {$result['updated']} murid.");
    }

    /**
     * Download CSV template for student import.
     */
    public function downloadTemplate(): StreamedResponse
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
            ['202401001', 'Ahmad Fauzi', 'X IPA 1'],
            ['202401002', 'Siti Nurhaliza', 'X IPA 1'],
            ['202401003', 'Budi Santoso', 'X IPS 2'],
            ['202401004', 'Dewi Lestari', 'X IPS 2'],
            ['202401005', 'Reza Rahadian', 'XI IPA 1'],
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
