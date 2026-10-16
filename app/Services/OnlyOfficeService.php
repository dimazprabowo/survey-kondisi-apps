<?php

namespace App\Services;

use App\Models\SurveyReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Integrasi OnlyOffice Document Server untuk edit DOCX laporan survey.
 *
 * Alur:
 *   1. Halaman editor memuat api.js dari Document Server + config hasil
 *      buildEditorConfig() (sudah termasuk token JWT).
 *   2. Document Server mengunduh DOCX via signed URL (route onlyoffice.report.file).
 *   3. Saat user menyimpan/menutup editor, Document Server POST callback
 *      (route onlyoffice.report.callback) dengan JWT di header Authorization.
 *   4. Hasil edit disimpan sebagai versi terpisah (edited_file_*) — file
 *      generate asli tetap utuh.
 */
class OnlyOfficeService
{
    public function __construct(protected FileStorageService $storage = new FileStorageService) {}

    public function enabled(): bool
    {
        return (bool) config('services.onlyoffice.enabled')
            && config('services.onlyoffice.url') !== ''
            && (string) config('services.onlyoffice.jwt_secret') !== '';
    }

    public function apiJsUrl(): string
    {
        return config('services.onlyoffice.url').'/web-apps/apps/api/documents/api.js';
    }

    /**
     * File yang dibuka di editor: versi edit terakhir bila ada (melanjutkan
     * pekerjaan), jika belum ada pakai file hasil generate.
     */
    public function editableSourcePath(SurveyReport $report): ?string
    {
        return $report->edited_file_path ?: $report->file_path;
    }

    /**
     * Key dokumen wajib berubah setiap konten berubah — jika tidak, Document
     * Server menyajikan versi cache lama setelah save.
     */
    public function documentKey(SurveyReport $report): string
    {
        $source = $this->editableSourcePath($report);
        $timestamp = $report->edited_at?->timestamp
            ?? $report->file_processed_at?->timestamp
            ?? $report->updated_at?->timestamp;

        return 'report-'.$report->id.'-'.substr(md5($source.'|'.$timestamp), 0, 16);
    }

    /**
     * Config lengkap untuk DocsAPI.DocEditor — sudah termasuk token JWT
     * yang diverifikasi Document Server.
     */
    public function buildEditorConfig(SurveyReport $report, User $user): array
    {
        // getRouteKey() dievaluasi sekali — ciphertext acak per enkripsi,
        // sementara signedRoute memanggil route() dua kali (signature +
        // URL final); lewat model akan menghasilkan ciphertext berbeda.
        $routeKey = $report->getRouteKey();

        $fileUrl = URL::temporarySignedRoute(
            'onlyoffice.report.file',
            now()->addHours(12),
            ['report' => $routeKey]
        );

        $config = [
            'documentType' => 'word',
            'type' => 'desktop',
            'document' => [
                'fileType' => 'docx',
                'key' => $this->documentKey($report),
                'title' => $report->edited_file_name ?: ($report->file_name ?: 'laporan.docx'),
                'url' => $fileUrl,
                'permissions' => ['edit' => true, 'download' => true, 'print' => true],
            ],
            'editorConfig' => [
                'callbackUrl' => route('onlyoffice.report.callback', ['report' => $routeKey]),
                'mode' => 'edit',
                'lang' => 'id',
                'user' => ['id' => (string) $user->id, 'name' => $user->name],
                'customization' => [
                    'autosave' => true,
                    'forcesave' => true,
                    'goback' => ['url' => route('surveys.report', $report->survey)],
                ],
            ],
        ];

        $config['token'] = $this->jwtEncode($config);

        return $config;
    }

    /**
     * Ambil & verifikasi payload callback: JWT dari header Authorization
     * (format OnlyOffice: "Bearer <jwt>", payload bisa dibungkus key
     * "payload" di versi Document Server lama).
     *
     * @return array<string, mixed>
     */
    public function callbackPayload(Request $request): array
    {
        $token = $request->bearerToken() ?: (string) $request->input('token', '');
        if ($token === '') {
            throw new \RuntimeException('Token callback tidak ditemukan.');
        }

        $decoded = $this->jwtDecode($token);

        if (isset($decoded['payload']) && is_array($decoded['payload'])) {
            $decoded = $decoded['payload'];
        }

        // Beberapa versi DS tetap mengirim field status di body mentah.
        if (! isset($decoded['status']) && $request->has('status')) {
            $decoded['status'] = $request->input('status');
            $decoded['url'] = $decoded['url'] ?? $request->input('url');
            $decoded['users'] = $decoded['users'] ?? $request->input('users', []);
        }

        return $decoded;
    }

    /**
     * Proses callback Document Server. Status:
     *   1 = sedang diedit, 2 = siap simpan (semua user keluar),
     *   3 = error simpan, 4 = tutup tanpa perubahan,
     *   6 = forcesave, 7 = error forcesave.
     * Status 2 & 6 membawa URL dokumen hasil edit yang diunduh ulang.
     */
    public function handleCallback(SurveyReport $report, array $payload): void
    {
        $status = (int) ($payload['status'] ?? 0);

        if (in_array($status, [3, 7], true)) {
            Log::warning('OnlyOffice gagal menyimpan dokumen', [
                'report_id' => $report->id,
                'status' => $status,
            ]);

            return;
        }

        if (! in_array($status, [2, 6], true)) {
            return;
        }

        $url = (string) ($payload['url'] ?? '');
        if ($url === '') {
            return;
        }

        $contents = Http::timeout(120)->get($url)->body();
        if ($contents === '') {
            Log::warning('OnlyOffice callback mengembalikan dokumen kosong', ['report_id' => $report->id]);

            return;
        }

        $users = $payload['users'] ?? [];
        $editorUserId = is_array($users) && $users !== [] ? (int) end($users) : null;
        if ($editorUserId === 0) {
            $editorUserId = null;
        }

        $this->storeEditedRevision($report, $contents, $editorUserId);
    }

    /**
     * Simpan hasil edit sebagai versi terpisah — file generate asli
     * (file_path) tidak disentuh. Versi edit sebelumnya dihapus dari
     * storage agar tidak menumpuk.
     */
    public function storeEditedRevision(SurveyReport $report, string $contents, ?int $editorUserId = null): void
    {
        $shipName = $report->survey?->ship?->name ?? 'kapal';
        $fileName = ($report->report_number ?: 'laporan').'-'.Str::slug($shipName).'-edited.docx';

        $stored = $this->storage->storeContent($contents, 'survey-reports', [$shipName], $fileName);

        if ($report->edited_file_path && $report->edited_file_path !== $stored['path']) {
            $this->storage->delete($report->edited_file_path);
        }

        $report->update([
            'edited_file_path' => $stored['path'],
            'edited_file_name' => $stored['name'],
            'edited_file_size' => $stored['size'],
            'edited_at' => now(),
            'edited_by' => $editorUserId ?? auth()->id(),
        ]);
    }

    public function jwtEncode(array $payload): string
    {
        $secret = (string) config('services.onlyoffice.jwt_secret');
        $segments = [
            $this->base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'])),
            $this->base64UrlEncode((string) json_encode($payload)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * @throws \RuntimeException Jika token/signature tidak valid.
     */
    public function jwtDecode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Format token tidak valid.');
        }

        [$header, $payload, $signature] = $parts;
        $secret = (string) config('services.onlyoffice.jwt_secret');
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header.'.'.$payload, $secret, true));

        if (! hash_equals($expected, $signature)) {
            throw new \RuntimeException('Signature token tidak valid.');
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('Payload token tidak valid.');
        }

        return $decoded;
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
