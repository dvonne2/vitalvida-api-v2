<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\FileUpload;

class FileUploadService
{
    /**
     * Handle file upload with validation and storage
     */
    public function uploadFile(
        UploadedFile $file, 
        string $directory, 
        string $uploadableType,
        int $uploadableId
    ): array {
        
        // Validate file
        $this->validateFile($file);

        // Generate unique filename
        $filename = $this->generateFilename($file, $uploadableType, $uploadableId);
        
        // Store file
        $path = $file->storeAs($directory, $filename, 'public');
        
        return [
            'success' => true,
            'file_path' => $path,
            'file_url' => Storage::url($path),
            'file_name' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType()
        ];
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        // File size limit: 5MB
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \Exception('File size cannot exceed 5MB');
        }

        // Allowed file types
        $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedTypes)) {
            throw new \Exception('File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
        }

        // Check if file is actually an image/document
        $mimeType = $file->getMimeType();
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/jpg',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new \Exception('Invalid file type detected');
        }
    }

    /**
     * Generate unique filename
     */
    private function generateFilename(UploadedFile $file, string $type, int $id): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $random = substr(md5(uniqid()), 0, 8);
        
        // Extract class name from full namespace
        $className = class_basename($type);
        
        return "{$className}_{$id}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Delete uploaded file
     */
    public function deleteFile(string $filePath): bool
    {
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }
        return false;
    }

    /**
     * Get file URL
     */
    public function getFileUrl(string $filePath): string
    {
        return Storage::url($filePath);
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $filePath): bool
    {
        return Storage::disk('public')->exists($filePath);
    }
} 