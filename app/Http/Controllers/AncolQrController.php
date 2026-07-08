<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AncolQrController extends Controller
{
    public function show(string $category)
    {
        $path = storage_path('app/public/' . $this->filename($category));
        abort_unless(file_exists($path), 404);

        return response()->file($path, ['Content-Type' => 'image/png']);
    }

    public function upload(Request $request, string $category)
    {
        $request->validate([
            'image' => 'required|image|mimes:png,jpg,jpeg|max:5120',
        ]);

        // Normalize to PNG so ticket generation and the gate-scanner display
        // always read the same format regardless of what was uploaded.
        $source = @imagecreatefromstring(file_get_contents($request->file('image')->getRealPath()));
        abort_unless($source, 422, 'File gambar tidak valid.');

        ob_start();
        imagepng($source);
        $bytes = ob_get_clean();
        unset($source);

        Storage::disk('public')->put($this->filename($category), $bytes);

        return response()->json(['message' => 'QR berhasil diperbarui.']);
    }

    private function filename(string $category): string
    {
        return "ancol-qr-{$category}.png";
    }
}
