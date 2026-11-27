<?php

namespace App\Http\Controllers\Tool;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tool\StoreExampleImageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExampleImageController extends Controller
{
    /**
     * Store a screenshot for the examples of a tool and answer with the URL to
     * put in the Markdown. If the record cannot be saved, the file is removed again.
     */
    public function store(StoreExampleImageRequest $request): JsonResponse
    {
        $file = $request->file('image');
        $path = $file->store('examples', 'public');

        if ($path === false) {
            throw new RuntimeException('The image could not be stored.');
        }

        try {
            $image = $request->user()->exampleImages()->create([
                'path' => $path,
                // The column holds 255 characters; a longer name from the client would fail the insert.
                'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return response()->json([
            'data' => [
                'url' => $image->url(),
                'original_name' => $image->original_name,
            ],
        ], Response::HTTP_CREATED);
    }
}
