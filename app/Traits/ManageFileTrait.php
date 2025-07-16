<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

trait ManageFileTrait
{
    function storeFile($file, $folder = 'uploads')
    {
        $fileName = time() . '_' . Str::random(8) . '.' . $file->extension();

        $file->move(storage_path('app/public/' . $folder), $fileName);

        $path = $folder . '/' . $fileName;

        return $path;
    }

    function updateFile($file, $folder, $oldPathFile)
    {
        $this->deleteFile($oldPathFile);
        return $this->storeFile($file, $folder);
    }

    function deleteFile($path)
    {
        // if (Storage::disk('public')->exists($path)) {
        //     Storage::disk('public')->delete($path);
        // }
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
