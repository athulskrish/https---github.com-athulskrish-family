<?php
class PhotoHandler {
    private static $uploadDir = 'assets/uploads/photos/';
    private static $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    private static $maxFileSize = 5 * 1024 * 1024; // 5MB

    public static function uploadPhoto($file, $memberId) {
        // Create upload directory if it doesn't exist
        if (!file_exists(self::$uploadDir)) {
            mkdir(self::$uploadDir, 0755, true);
        }

        // Validate file
        if (!self::validateFile($file)) {
            return ['success' => false, 'error' => 'Invalid file'];
        }

        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $memberId . '_' . time() . '.' . $extension;
        $filepath = self::$uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => true, 'filename' => $filename];
        }

        return ['success' => false, 'error' => 'Upload failed'];
    }

    private static function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) return false;
        if ($file['size'] > self::$maxFileSize) return false;
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        return in_array($extension, self::$allowedTypes);
    }
}
?>