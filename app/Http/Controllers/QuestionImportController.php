<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SimpleXMLElement;
use ZipArchive;

class QuestionImportController extends Controller
{
    /**
     * Parse uploaded .docx file and return parsed questions.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:docx', 'max:5120'], // Max 5MB
        ]);

        $file = $request->file('file');
        $realPath = $file->getRealPath();

        $zip = new ZipArchive;
        if ($zip->open($realPath) !== true) {
            return response()->json([
                'message' => 'Gagal membuka file Word (.docx). File mungkin rusak.',
            ], 422);
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! $documentXml) {
            return response()->json([
                'message' => 'Format file Word tidak valid atau tidak memiliki konten teks.',
            ], 422);
        }

        // Parse XML to extract paragraphs
        $paragraphs = [];
        // Suppress errors for malformed XML just in case
        libxml_use_internal_errors(true);
        try {
            $xml = new SimpleXMLElement($documentXml);
            $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $pNodes = $xml->xpath('//w:p');

            if ($pNodes) {
                foreach ($pNodes as $pNode) {
                    $pText = '';
                    $tNodes = $pNode->xpath('.//w:t');
                    if ($tNodes) {
                        foreach ($tNodes as $tNode) {
                            $pText .= (string) $tNode;
                        }
                    }
                    $pText = trim($pText);
                    if ($pText !== '') {
                        $paragraphs[] = $pText;
                    }
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengurai isi file Word: '.$e->getMessage(),
            ], 422);
        }

        if (empty($paragraphs)) {
            return response()->json([
                'message' => 'Tidak ditemukan teks/pertanyaan di dalam file Word.',
            ], 422);
        }

        // Parse paragraphs to questions
        $questions = [];
        $currentQuestion = null;
        $nextQuestionId = 1000; // Large temp ID to avoid collision with frontend IDs

        foreach ($paragraphs as $text) {
            // Check if it's a question number, e.g. "1. Apa..." or "1) Apa..."
            if (preg_match('/^\d+[\.\)]\s*(.*)$/i', $text, $matches)) {
                if ($currentQuestion) {
                    $questions[] = $currentQuestion;
                }
                $currentQuestion = [
                    'id' => $nextQuestionId++,
                    'title' => trim($matches[1]) ?: $text,
                    'description' => '',
                    'type' => 'Multiple choice',
                    'options' => [],
                    'answer' => '',
                    'required' => false,
                    'points' => 10,
                ];
            }
            // Check if it is an option, e.g. "A. ..." or "B) ..."
            elseif (preg_match('/^([A-E])[\.\)]\s*(.*)$/i', $text, $matches)) {
                if ($currentQuestion) {
                    $currentQuestion['options'][] = trim($matches[2]);
                }
            }
            // Check if it specifies the correct answer, e.g. "Jawaban: B"
            elseif (preg_match('/^(jawaban|kunci|answer|key)\s*:\s*(.*)$/i', $text, $matches)) {
                if ($currentQuestion) {
                    $currentQuestion['raw_answer'] = trim($matches[2]);
                }
            }
            // Otherwise, append to question title if no options yet
            else {
                if ($currentQuestion && empty($currentQuestion['options'])) {
                    $currentQuestion['title'] .= "\n".$text;
                }
            }
        }

        if ($currentQuestion) {
            $questions[] = $currentQuestion;
        }

        // Post-process questions to normalize answers and set types
        foreach ($questions as &$q) {
            if (empty($q['options'])) {
                $q['type'] = 'Short answer';
                $q['answer'] = $q['raw_answer'] ?? '';
            } else {
                $q['type'] = 'Multiple choice';
                $raw = $q['raw_answer'] ?? '';
                if (preg_match('/^([A-E])$/i', $raw, $letterMatches)) {
                    $letter = strtoupper($letterMatches[1]);
                    $letterToIndex = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
                    $q['answer'] = $letterToIndex[$letter];
                } else {
                    $foundIndex = -1;
                    foreach ($q['options'] as $idx => $opt) {
                        if (strcasecmp(trim($opt), $raw) === 0) {
                            $foundIndex = $idx;
                            break;
                        }
                    }
                    if ($foundIndex !== -1) {
                        $q['answer'] = $foundIndex;
                    } else {
                        if (preg_match('/^([A-E])[\.\)]\s*(.*)$/i', $raw, $rawLetterMatches)) {
                            $letter = strtoupper($rawLetterMatches[1]);
                            $letterToIndex = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
                            $q['answer'] = $letterToIndex[$letter];
                        } else {
                            $q['answer'] = 0; // Default to first option
                        }
                    }
                }
            }
            unset($q['raw_answer']);
        }

        if (empty($questions)) {
            return response()->json([
                'message' => 'Format pertanyaan di dalam file tidak sesuai petunjuk. Pastikan soal diawali dengan nomor (misal: "1. Soal") dan pilihan diawali huruf (misal: "A. Pilihan").',
            ], 422);
        }

        return response()->json([
            'questions' => $questions,
        ]);
    }
}
