<?php

namespace App\Http\Controllers;

use App\Features\DocumentFeatures;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Display a listing of the documents.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check if the feature is enabled for the current tenant
        $documentFeatures = new DocumentFeatures;
        if (! $documentFeatures('list')) {
            return response([
                'message' => 'This feature is not available for your tenant.',
            ], 403);
        }

        $user = $request->user();

        // Check if the user has admin or company_admin permissions
        if (! $user->is_admin && ! $user->is_company_admin) {
            return response([
                'message' => 'You do not have permission to view documents.',
            ], 403);
        }

        // Get the selected company for the user
        $selectedCompany = $user->selectedCompany();
        if (! $selectedCompany) {
            return response([
                'message' => 'No company selected.',
            ], 400);
        }

        // Apply filters if provided
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

            // If no mime types are selected, include all
            if (count($mimeTypeIn) == 0) {
                $mimeTypeIn = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/zip',
                ];
            }

            // If no date filters are selected, include all
            if (count($createdAtFilter) == 0) {
                $createdAtFilter = [['created_at', '>=', '2000-01-01']];
            }

            // Query documents with filters
            $query = Document::where('type', 'file')
                ->whereIn('mime_type', $mimeTypeIn)
                ->where($createdAtFilter);
        } else {
            // Get documents in the specified folder
            $folder = base64_decode($request->folder ?? '');
            $query = Document::where('path', $folder);
        }

        // Filter by company_id based on user permissions
        if ($user->is_admin) {
            // Admin can see all documents if company_id is specified, otherwise only the selected company
            if (isset($request->company_id)) {
                $query->where('company_id', $request->company_id);
            } else {
                $query->where('company_id', $selectedCompany->id);
            }
        } else {
            // Company admin can only see documents for their selected company
            $query->where('company_id', $selectedCompany->id);
        }

        // Include uploader information and order the results
        $documents = $query->with('uploader')
            ->orderByRaw("type = 'folder' DESC")
            ->orderBy('created_at', 'asc')
            ->get();

        return response([
            'documents' => $documents,
        ], 200);
    }

    /**
     * Store a newly created document or folder in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Check if the feature is enabled for the current tenant
        $documentFeatures = new DocumentFeatures;
        if (! $documentFeatures('upload')) {
            return response([
                'message' => 'This feature is not available for your tenant.',
            ], 403);
        }

        $user = $request->user();

        // Only admin users can upload documents
        if ($user->is_admin != 1) {
            return response([
                'message' => 'Only administrators can upload documents.',
            ], 403);
        }

        // Validate the request data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:file,folder',
            'path' => 'required|string',
            'company_id' => 'required|exists:companies,id',
        ]);

        // Handle folder creation
        if ($validated['type'] == 'folder') {
            $name = $request->name;

            $document = Document::create([
                'name' => $name,
                'uploaded_name' => $name,
                'type' => $validated['type'],
                'mime_type' => 'folder',
                'path' => $validated['path'],
                'company_id' => $validated['company_id'],
                'uploaded_by' => $user->id,
                'file_size' => 0,
            ]);

            return response([
                'document' => $document,
                'message' => 'Folder created successfully.',
            ], 201);
        }
        // Handle file upload
        else {
            if ($request->file('file') != null) {
                $file = $request->file('file');

                // Generate a unique filename
                $uploaded_name = time().'_'.$file->getClientOriginalName();
                $mime_type = $file->getClientMimeType();
                $file_size = $file->getSize();

                // Store the file in Google Cloud Storage
                $bucket_path = 'documents'.$validated['path'].'';
                FileUploadController::storeFile($file, $bucket_path, $uploaded_name);

                // Create the document record in the database
                $document = Document::create([
                    'name' => $validated['name'],
                    'uploaded_name' => $uploaded_name,
                    'type' => $validated['type'],
                    'mime_type' => $mime_type,
                    'path' => $validated['path'],
                    'company_id' => $validated['company_id'],
                    'uploaded_by' => $user->id,
                    'file_size' => $file_size,
                ]);

                return response([
                    'document' => $document,
                    'message' => 'Document uploaded successfully.',
                ], 201);
            } else {
                return response([
                    'message' => 'File is required for document upload.',
                ], 400);
            }
        }
    }

    /**
     * Generate a temporary URL for downloading a document.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadFile(Document $document, Request $request)
    {
        // Check if the feature is enabled for the current tenant
        $documentFeatures = new DocumentFeatures;
        if (! $documentFeatures('download')) {
            return response([
                'message' => 'This feature is not available for your tenant.',
            ], 403);
        }

        $user = $request->user();

        // Check if the user has admin or company_admin permissions
        if (! $user->is_admin && ! $user->is_company_admin) {
            return response([
                'message' => 'You do not have permission to download documents.',
            ], 403);
        }

        // Check if the user has access to the document's company
        $selectedCompany = $user->selectedCompany();
        if (! $selectedCompany || $document->company_id != $selectedCompany->id) {
            // Admin users can access any document
            if (! $user->is_admin) {
                return response([
                    'message' => 'You do not have access to this document.',
                ], 403);
            }
        }

        // Check if the document is a file (not a folder)
        if ($document->type !== 'file') {
            return response([
                'message' => 'Cannot generate download URL for a folder.',
            ], 400);
        }

        try {
            // Generate a temporary URL for the document
            $url = FileUploadController::generateSignedUrlForFile(
                'documents'.$document->path.$document->uploaded_name
            );

            return response([
                'url' => $url,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error generating download URL: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified document from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Document $document, Request $request)
    {
        // Check if the feature is enabled for the current tenant
        $documentFeatures = new DocumentFeatures;
        if (! $documentFeatures('delete')) {
            return response([
                'message' => 'This feature is not available for your tenant.',
            ], 403);
        }

        $user = $request->user();

        // Only admin users can delete documents
        if ($user->is_admin != 1) {
            return response([
                'message' => 'Only administrators can delete documents.',
            ], 403);
        }

        // Check if the document exists
        if (! $document) {
            return response([
                'message' => 'Document not found.',
            ], 404);
        }

        try {
            // If it's a file, delete it from storage
            if ($document->type === 'file') {
                $filePath = 'documents'.$document->path.$document->uploaded_name;

                // Check if the file exists in storage
                if (Storage::disk('gcs')->exists($filePath)) {
                    // Delete the file from storage
                    Storage::disk('gcs')->delete($filePath);
                }
            }
            // If it's a folder, check if it's empty
            elseif ($document->type === 'folder') {
                // Check if there are any documents in this folder
                $hasDocuments = Document::where('path', $document->path.$document->name.'/')->exists();

                if ($hasDocuments) {
                    return response([
                        'message' => 'Cannot delete folder because it is not empty.',
                    ], 400);
                }
            }

            // Delete the document record from the database
            $document->delete();

            return response([
                'message' => 'Document deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error deleting document: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search for documents by name or content.
     *
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        // Check if the feature is enabled for the current tenant
        $documentFeatures = new DocumentFeatures;
        if (! $documentFeatures('search')) {
            return response([
                'message' => 'This feature is not available for your tenant.',
            ], 403);
        }

        $user = $request->user();

        // Check if the user has admin or company_admin permissions
        if (! $user->is_admin && ! $user->is_company_admin) {
            return response([
                'message' => 'You do not have permission to search documents.',
            ], 403);
        }

        // Get the search query
        $search = $request->search;
        if (! $search) {
            return response([
                'message' => 'Search query is required.',
            ], 400);
        }

        // Get the selected company for the user
        $selectedCompany = $user->selectedCompany();
        if (! $selectedCompany) {
            return response([
                'message' => 'No company selected.',
            ], 400);
        }

        try {
            // Perform the search using Laravel Scout
            $query = Document::query()->when($search, function (Builder $q, $value) {
                return $q->whereIn('id', Document::search($value)->keys());
            });

            // Filter by company_id based on user permissions
            if ($user->is_admin) {
                // Admin can search all documents if company_id is specified, otherwise only the selected company
                if (isset($request->company_id)) {
                    $query->where('company_id', $request->company_id);
                } else {
                    $query->where('company_id', $selectedCompany->id);
                }
            } else {
                // Company admin can only search documents for their selected company
                $query->where('company_id', $selectedCompany->id);
            }

            // Include uploader information
            $documents = $query->with('uploader')->get();

            return response([
                'documents' => $documents,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error searching documents: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search for documents by company.
     *
     * @param  int  $companyId
     * @return \Illuminate\Http\Response
     */
    public function searchByCompany(Request $request, $companyId)
    {
        // Check if the feature is enabled for the current tenant
        $documentFeatures = new DocumentFeatures;
        if (! $documentFeatures('search')) {
            return response([
                'message' => 'This feature is not available for your tenant.',
            ], 403);
        }

        $user = $request->user();

        // Check if the user has admin permissions
        if (! $user->is_admin) {
            return response([
                'message' => 'Only administrators can search documents by company.',
            ], 403);
        }

        // Get the search query
        $search = $request->search;
        if (! $search) {
            return response([
                'message' => 'Search query is required.',
            ], 400);
        }

        try {
            // Perform the search using Laravel Scout
            $documents = Document::query()
                ->when($search, function (Builder $q, $value) {
                    return $q->whereIn('id', Document::search($value)->keys());
                })
                ->where('company_id', $companyId)
                ->with('uploader')
                ->get();

            return response([
                'documents' => $documents,
            ], 200);
        } catch (\Exception $e) {
            return response([
                'message' => 'Error searching documents: '.$e->getMessage(),
            ], 500);
        }
    }
}
