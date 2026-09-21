<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class StudentImportService
{
    /**
     * Parse raw text or file content into normalized student rows.
     *
     * @return array{
     *     valid: list<array{nis: string, name: string, kelas: string, default_password: string, email: string, is_update: bool}>,
     *     errors: list<array{row: int, line: string, message: string}>,
     *     total_rows: int
     * }
     */
    public function parseContent(string $content): array
    {
        // Normalize line endings
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) {
            return [
                'valid' => [],
                'errors' => [],
                'total_rows' => 0,
            ];
        }

        // Determine delimiter based on first few lines
        $delimiter = $this->detectDelimiter($lines);

        $valid = [];
        $errors = [];
        $headerIndices = null;
        $seenNis = [];

        // Check existing NIS in DB to identify new vs existing
        $existingNis = User::whereNotNull('nis')->pluck('id', 'nis')->all();

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $columns = str_getcsv($trimmedLine, $delimiter);
            $columns = array_map(fn ($c) => trim((string) $c, " \t\n\r\0\x0B\"'"), $columns);

            // Check if this line is header row
            if ($headerIndices === null) {
                $possibleHeaders = $this->extractHeaderIndices($columns);
                if ($possibleHeaders !== null) {
                    $headerIndices = $possibleHeaders;

                    continue; // Skip the header row itself
                } else {
                    // Default column order: 0 = NIS, 1 = NAMA, 2 = KELAS
                    $headerIndices = ['nis' => 0, 'nama' => 1, 'kelas' => 2];
                }
            }

            $nisIndex = $headerIndices['nis'] ?? 0;
            $nameIndex = $headerIndices['nama'] ?? 1;
            $classIndex = $headerIndices['kelas'] ?? 2;

            $nis = $columns[$nisIndex] ?? '';
            $name = $columns[$nameIndex] ?? '';
            $kelas = $columns[$classIndex] ?? '';

            $rowNumber = $index + 1;

            if ($nis === '' && $name === '') {
                continue;
            }

            if ($nis === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'line' => $trimmedLine,
                    'message' => 'NIS tidak boleh kosong.',
                ];

                continue;
            }

            if ($name === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'line' => $trimmedLine,
                    'message' => 'Nama tidak boleh kosong.',
                ];

                continue;
            }

            if (isset($seenNis[$nis])) {
                $errors[] = [
                    'row' => $rowNumber,
                    'line' => $trimmedLine,
                    'message' => "Duplikasi NIS '{$nis}' ditemukan di baris {$rowNumber}.",
                ];

                continue;
            }

            $seenNis[$nis] = true;
            $isUpdate = isset($existingNis[$nis]);

            $valid[] = [
                'nis' => (string) $nis,
                'name' => (string) $name,
                'kelas' => (string) $kelas,
                'email' => ! empty($columns[3]) && filter_var($columns[3], FILTER_VALIDATE_EMAIL) ? $columns[3] : null,
                'is_update' => $isUpdate,
            ];
        }

        return [
            'valid' => $valid,
            'errors' => $errors,
            'total_rows' => count($valid) + count($errors),
        ];
    }

    /**
     * Parse content from uploaded file.
     *
     * @return array{
     *     valid: list<array{nis: string, name: string, kelas: string, default_password: string, email: ?string, is_update: bool}>,
     *     errors: list<array{row: int, line: string, message: string}>,
     *     total_rows: int
     * }
     */
    public function parseFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());

        return $this->parseContent($content ?: '');
    }

    /**
     * Execute student import and save to database.
     *
     * @param  list<array{nis: string, name: string, kelas: string, default_password: string, email: ?string, is_update: bool}>  $students
     * @return array{
     *     created: int,
     *     updated: int,
     *     total: int
     * }
     */
    public function import(array $students): array
    {
        $created = 0;
        $updated = 0;

        foreach ($students as $student) {
            $nis = (string) $student['nis'];
            $name = (string) $student['name'];
            $kelas = (string) ($student['kelas'] ?? '');
            $defaultPassword = User::defaultPasswordForNis($nis);
            $email = ! empty($student['email']) ? $student['email'] : null;

            $existing = User::where('nis', $nis)->first();

            if ($existing) {
                $existing->update([
                    'name' => $name,
                    'kelas' => $kelas,
                    'role' => 'siswa',
                    'email' => $email ?: $existing->email,
                ]);
                $updated++;
            } else {
                User::create([
                    'name' => $name,
                    'nis' => $nis,
                    'kelas' => $kelas,
                    'email' => $email,
                    'password' => Hash::make($defaultPassword),
                    'must_change_password' => true,
                    'role' => 'siswa',
                    'is_admin' => false,
                ]);
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $created + $updated,
        ];
    }

    /**
     * Detect CSV delimiter (comma, semicolon, or tab).
     *
     * @param  list<string>  $lines
     */
    protected function detectDelimiter(array $lines): string
    {
        $sample = implode("\n", array_slice($lines, 0, 5));
        $tabCount = substr_count($sample, "\t");
        $commaCount = substr_count($sample, ',');
        $semicolonCount = substr_count($sample, ';');

        if ($tabCount > $commaCount && $tabCount > $semicolonCount) {
            return "\t";
        }

        if ($semicolonCount > $commaCount) {
            return ';';
        }

        return ',';
    }

    /**
     * Try to extract column indices from headers.
     *
     * @param  list<string>  $columns
     * @return array{nis: int, nama: int, kelas: int}|null
     */
    protected function extractHeaderIndices(array $columns): ?array
    {
        $nisIndex = null;
        $nameIndex = null;
        $classIndex = null;

        foreach ($columns as $idx => $rawHeader) {
            $header = strtolower(trim($rawHeader));

            if (in_array($header, ['nis', 'no induk', 'nomor induk', 'nomor induk siswa', 'student_id', 'nisn', 'id_murid'], true)) {
                $nisIndex = $idx;
            } elseif (in_array($header, ['nama', 'name', 'nama lengkap', 'nama murid', 'nama siswa', 'student_name'], true)) {
                $nameIndex = $idx;
            } elseif (in_array($header, ['kelas', 'class', 'rombel', 'tingkat', 'kelas_siswa'], true)) {
                $classIndex = $idx;
            }
        }

        if ($nisIndex !== null || $nameIndex !== null) {
            return [
                'nis' => $nisIndex ?? 0,
                'nama' => $nameIndex ?? 1,
                'kelas' => $classIndex ?? 2,
            ];
        }

        return null;
    }
}
