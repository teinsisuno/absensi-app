<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pengumuman — semua user login baca yang sudah publish; admin kelola (termasuk draft).
 */
class AnnouncementController extends Controller
{
    /**
     * GET /api/v1/announcements — auth: published announcements, terbaru dulu.
     * Untuk admin (superadmin/hr) ikut menampilkan draft agar bisa dikelola dari web.
     */
    public function index(Request $request): JsonResponse
    {
        $isAdmin = in_array($request->user()->role, ['superadmin', 'hr'], true);

        $announcements = Announcement::with('creator:id,name')
            ->when(! $isAdmin, fn ($q) => $q->whereNotNull('published_at'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $announcements]);
    }

    /**
     * GET /api/v1/announcements/{id} — auth: detail satu pengumuman.
     * Non-admin hanya bisa melihat yang sudah publish (selain itu 404).
     */
    public function show(Request $request, Announcement $announcement): JsonResponse
    {
        $isAdmin = in_array($request->user()->role, ['superadmin', 'hr'], true);

        if (! $isAdmin && ! $announcement->published_at) {
            abort(404, 'Pengumuman tidak ditemukan.');
        }

        $announcement->load('creator:id,name');

        return response()->json(['data' => $announcement]);
    }

    /**
     * GET /api/v1/announcements/latest — auth: 5 pengumuman terbaru (dashboard mobile).
     */
    public function latest(Request $request): JsonResponse
    {
        $announcements = Announcement::with('creator:id,name')
            ->whereNotNull('published_at')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return response()->json(['data' => $announcements]);
    }

    /**
     * POST /api/v1/announcements — admin: buat pengumuman (published_at kosong = draft).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'published_at' => ! empty($validated['publish']) ? now() : null,
        ]);

        return response()->json([
            'message' => $announcement->published_at ? 'Pengumuman dipublikasikan.' : 'Pengumuman disimpan sebagai draft.',
            'data' => $announcement->fresh('creator:id,name'),
        ], 201);
    }

    /**
     * PUT /api/v1/announcements/{announcement} — admin: edit pengumuman.
     */
    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'published_at' => $request->filled('publish') ? now() : $announcement->published_at,
        ]);

        return response()->json(['data' => $announcement->fresh('creator:id,name')]);
    }

    /**
     * DELETE /api/v1/announcements/{announcement} — admin: hapus pengumuman.
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json(['message' => 'Pengumuman dihapus.']);
    }
}
