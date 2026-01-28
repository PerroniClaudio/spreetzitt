<?php

namespace App\Http\Controllers;

use App\Models\WikiObject;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WikiObjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        if (isset($request->filter) && ($request->filter != 'all')) {

            $filters = json_decode($request->filter, true);

            $mimeTypeIn = [];
            $createdAtFilter = [];

            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'pdf':
                        if ($value) {
                            $mimeTypeIn[] = 'application/pdf';
                        }
                        break;
                    case 'word':
                        if ($value) {
                            $mimeTypeIn[] = 'application/msword';
                            $mimeTypeIn[] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        }
                        break;
                    case 'excel':
                        if ($value) {
                            $mimeTypeIn[] = 'application/vnd.ms-excel';
                            $mimeTypeIn[] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                        }
                        break;
                    case 'powerpoint':
                        if ($value) {
                            $mimeTypeIn[] = 'application/vnd.ms-powerpoint';
                            $mimeTypeIn[] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
                        }
                        break;
                    case 'archive':
                        if ($value) {
                            $mimeTypeIn[] = 'application/zip';
                        }
                        break;
                    case 'dateFrom':
                        if ($value) {
                            $createdAtFilter[] = ['created_at', '>=', $value];
                        }
                        break;
                    case 'dateTo':
                        if ($value) {
                            $createdAtFilter[] = ['created_at', '<=', $value];
                        }
                        break;
                }
            }

            if (count($mimeTypeIn) == 0) {
                $mimeTypeIn = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'];
            }

            if (count($createdAtFilter) == 0) {
                $createdAtFilter = [['created_at', '>=', '2000-01-01']];
            }

            $wikiObjects = WikiObject::where('type', 'file')
                ->whereIn('mime_type', $mimeTypeIn)
                ->where($createdAtFilter)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();
        } else {

            $folder = base64_decode($request->folder);
            $wikiObjects = WikiObject::where('path', $folder)
                ->with('user')
                ->orderByRaw("type = 'folder' DESC")
                ->orderBy('created_at', 'asc')
                ->get();
        }

        return response([
            'files' => $wikiObjects,
        ], 200);
    }

    public function public(Request $request)
    {

        $user = $request->user();

        if (isset($request->filter) && ($request->filter != 'all')) {

            $filters = json_decode($request->filter, true);

            $mimeTypeIn = [];
            $createdAtFilter = [];

            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'pdf':
                        if ($value) {
                            $mimeTypeIn[] = 'application/pdf';
                        }
                        break;
                    case 'word':
                        if ($value) {
                            $mimeTypeIn[] = 'application/msword';
                            $mimeTypeIn[] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                        }
                        break;
                    case 'excel':
                        if ($value) {
                            $mimeTypeIn[] = 'application/vnd.ms-excel';
                            $mimeTypeIn[] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                        }
                        break;
                    case 'powerpoint':
                        if ($value) {
                            $mimeTypeIn[] = 'application/vnd.ms-powerpoint';
                            $mimeTypeIn[] = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
                        }
                        break;
                    case 'archive':
                        if ($value) {
                            $mimeTypeIn[] = 'application/zip';
                        }
                        break;
                    case 'dateFrom':
                        if ($value) {
                            $createdAtFilter[] = ['created_at', '>=', $value];
                        }
                        break;
                    case 'dateTo':
                        if ($value) {
                            $createdAtFilter[] = ['created_at', '<=', $value];
                        }
                        break;
                }
            }

            if (count($mimeTypeIn) == 0) {
                $mimeTypeIn = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'];
            }

            if (count($createdAtFilter) == 0) {
                $createdAtFilter = [['created_at', '>=', '2000-01-01']];
            }

            $wikiObjects = WikiObject::where('type', 'file')
                ->whereIn('mime_type', $mimeTypeIn)
                ->where($createdAtFilter)
                ->where('is_public', 1)
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();
        } else {

            $folder = base64_decode($request->folder);
            $wikiObjects = WikiObject::where('path', $folder)
                ->where('is_public', 1)
                ->with('user')
                ->orderByRaw("type = 'folder' DESC")
                ->orderBy('created_at', 'asc')
                ->get();
        }

        return response([
            'files' => $wikiObjects,
        ], 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return response([
            'message' => 'Please use /api/store to create a new object',
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //

        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:file,folder',
            'path' => 'required|string',
            'is_public' => 'required|boolean',
        ]);

        $company_id = isset($request->company_id) ? $request->company_id : null;

        if ($validated['type'] == 'folder') {
            $name = $request->name;

            $wikiObject = WikiObject::create([
                'name' => $name,
                'uploaded_name' => $name,
                'type' => $validated['type'],
                'mime_type' => 'folder',
                'path' => $validated['path'],
                'is_public' => $validated['is_public'],
                'company_id' => null,
                'uploaded_by' => $user->id,
                'file_size' => 0,
            ]);

            return response([
                'wikiObject' => $wikiObject,
            ], 201);
        } else {
            if ($request->file('file') != null) {

                $file = $request->file('file');

                $uploaded_name = time().'_'.$file->getClientOriginalName();
                $mime_type = $file->getClientMimeType();
                $file_size = $file->getSize();
                $db_path = $validated['path'];
                $bucket_path = FileUploadController::storagePathPrefix().'wiki_objects'.$validated['path'].'';
                FileUploadController::storeFile($file, $bucket_path, $uploaded_name);

                $company_id = isset($request->company_id) ? $request->company_id : null;
                $wikiObject = WikiObject::create([
                    'name' => $validated['name'],
                    'uploaded_name' => $uploaded_name,
                    'type' => $validated['type'],
                    'mime_type' => $mime_type,
                    'path' => $db_path,
                    'is_public' => $validated['is_public'],
                    'company_id' => $company_id,
                    'uploaded_by' => $user->id,
                    'file_size' => $file_size,
                ]);

                return response([
                    'wikiObject' => $wikiObject,
                ], 201);
            } else {
                return response([
                    'message' => 'File is required.',
                ], 400);
            }
        }
    }

    public function downloadFile(WikiObject $wikiObject)
    {
        $url = FileUploadController::generateSignedUrlForFile(
            'wiki_objects'.$wikiObject->path.$wikiObject->uploaded_name
        );

        return response([
            'url' => $url,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WikiObject $wikiObject, Request $request)
    {
        //

        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $wikiObject->delete();

        return response([
            'message' => 'WikiObject soft deleted successfully.',
        ], 200);
    }

    public function searchPublic(Request $request)
    {
        $user = $request->user();

        $search = $request->search;

        $wikiObjects = WikiObject::query()->when($search, function (Builder $q, $value) {
            /**
             * @disregard Intelephense non rileva il metodo whereIn
             */
            return $q->whereIn('id', WikiObject::search($value)->keys());
        })->where('is_public', 1)->with('user')->get();

        return response([
            'files' => $wikiObjects,
        ], 200);
    }

    public function search(Request $request)
    {
        $user = $request->user();

        if ($user['is_admin'] != 1) {
            return response([
                'message' => 'The user must be an admin.',
            ], 401);
        }

        $search = $request->search;

        $wikiObjects = WikiObject::query()->when($search, function (Builder $q, $value) {
            /**
             * @disregard Intelephense non rileva il metodo whereIn
             */
            return $q->whereIn('id', WikiObject::search($value)->keys());
        })->with('user')->get();

        return response([
            'files' => $wikiObjects,
        ], 200);
    }
}
