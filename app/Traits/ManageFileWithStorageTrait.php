<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage; // <-- Pastikan ini di-import
use Illuminate\Http\UploadedFile;

trait ManageFileWithStorageTrait
{
    function storeFile(UploadedFile $file, string $folder = 'uploads'): string
    {
        // putFile atau store akan otomatis membuat nama unik (hash)
        // dan mengembalikan path lengkapnya.
        return $file->store($folder, 'public');
    }

    /**
     * Mengupdate file: hapus yang lama, simpan yang baru.
     */
    function updateFile(UploadedFile $file, string $folder, ?string $oldPathFile): string
    {
        // Hapus file lama jika ada
        if ($oldPathFile) {
            $this->deleteFile($oldPathFile);
        }

        // Simpan file baru
        return $this->storeFile($file, $folder);
    }

    /**
     * Menghapus file dari storage.
     */
    function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
