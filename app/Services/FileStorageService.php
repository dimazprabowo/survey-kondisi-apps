<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Centralized file storage service.
 *
 * Single source of truth untuk konvensi penyimpanan file di seluruh aplikasi.
 * Menghindari duplikasi logic path-builder di setiap Job/Service.
 *
 * Konvensi path final:
 *   {app_env}/{fitur}/{...segments}/{slug-nama}_{YmdHis}.{ext}
 *   contoh: local/personel-certificates/budi-santoso/sertifikat-k3_20260720101500.pdf
 *
 * Alur pemakaian (async via worker):
 *   1. Component  : $temp = app(FileStorageService::class)->storeTemp($file, 'personel-certificates');
 *   2. Service    : simpan record status 'processing', dispatch Job dengan $temp['path'] & $temp['original_name'].
 *   3. Job (queue): $result = app(FileStorageService::class)->moveFromTemp($tempPath, $originalName, 'personel-certificates', [$personelSlug]);
 *                   lalu update record dengan $result['path'|'name'|'size'] + status 'completed'.
 */
class FileStorageService
{
    /**
     * Disk sementara untuk menampung file hasil upload Livewire sebelum diproses worker.
     */
    protected string $tempDisk = 'local';

    /**
     * Simpan UploadedFile ke lokasi sementara (disk lokal).
     *
     * @return array{path: string, original_name: string}
     */
    public function storeTemp(UploadedFile $file, string $feature): array
    {
        $path = $file->store('temp/'.trim($feature, '/'), $this->tempDisk);

        return [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Bangun path tujuan final sesuai konvensi aplikasi.
     *
     * @param  string  $feature  Nama menu/fitur, mis. 'personel-certificates'
     * @param  array<string>  $segments  Segmen tambahan (mis. slug item), akan di-slug otomatis
     * @param  string  $originalName  Nama file asli (dipakai untuk ambil ekstensi & base name)
     * @param  string|null  $baseName  Override nama dasar (default: dari originalName)
     */
    public function buildPath(string $feature, array $segments, string $originalName, ?string $baseName = null): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $base = Str::slug($baseName ?? pathinfo($originalName, PATHINFO_FILENAME)) ?: 'file';
        $fileName = $base.'_'.now()->format('YmdHis').($extension ? '.'.$extension : '');

        $parts = array_filter(array_map(
            fn ($segment) => Str::slug((string) $segment),
            $segments
        ), fn ($segment) => $segment !== '');

        return implode('/', array_merge(
            [strtolower((string) config('app.env', 'local')), trim($feature, '/')],
            $parts,
            [$fileName]
        ));
    }

    /**
     * Pindahkan file dari lokasi sementara ke disk permanen sesuai konvensi.
     * Menghapus file temp setelah berhasil.
     *
     * @param  array<string>  $segments
     * @return array{path: string, name: string, size: int}
     *
     * @throws \RuntimeException Jika file temp tidak ada atau gagal disimpan.
     */
    public function moveFromTemp(
        string $tempPath,
        string $originalName,
        string $feature,
        array $segments = [],
        ?string $baseName = null
    ): array {
        if (! Storage::disk($this->tempDisk)->exists($tempPath)) {
            throw new \RuntimeException('Temporary file not found: '.$tempPath);
        }

        $content = Storage::disk($this->tempDisk)->get($tempPath);
        $size = strlen($content);

        $destination = $this->buildPath($feature, $segments, $originalName, $baseName);
        $disk = file_disk();

        Storage::disk($disk)->put($destination, $content);

        if (! Storage::disk($disk)->exists($destination)) {
            throw new \RuntimeException('Failed to store file to disk ['.$disk.']: '.$destination);
        }

        Storage::disk($this->tempDisk)->delete($tempPath);

        return [
            'path' => $destination,
            'name' => $originalName,
            'size' => $size,
        ];
    }

    /**
     * Hapus file permanen jika ada. Aman dipanggil dengan path null/kosong.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $disk = file_disk();

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Hapus file temporary jika ada (mis. saat rollback/gagal proses).
     */
    public function deleteTemp(?string $tempPath): void
    {
        if ($tempPath && Storage::disk($this->tempDisk)->exists($tempPath)) {
            Storage::disk($this->tempDisk)->delete($tempPath);
        }
    }

    /**
     * Response download untuk file permanen.
     */
    public function download(string $path, ?string $downloadName = null)
    {
        return Storage::disk(file_disk())->download($path, $downloadName);
    }

    public function exists(?string $path): bool
    {
        return $path ? Storage::disk(file_disk())->exists($path) : false;
    }

    public function tempDisk(): string
    {
        return $this->tempDisk;
    }
}
