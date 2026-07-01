<?php
/**
 * Render a book cover — shows uploaded image if available, else gradient fallback.
 *
 * @param array  $book      Book row from DB (needs color1, color2, color3, cover_image, judul)
 * @param string $class     CSS class for the outer div
 * @param string $style     Extra inline CSS for the outer div
 * @param string $textStyle Extra inline CSS for text overlay
 * @param bool   $showText  Show title text on gradient covers
 */
function renderBookCover(array $book, string $class = '', string $style = '', string $textStyle = '', bool $showText = true): void {
    $img = $book['cover_image'] ?? '';
    $baseUrl = '/libfisip/uploads/covers/';
    if ($img && file_exists($_SERVER['DOCUMENT_ROOT'] . $baseUrl . $img)) {
        // Image cover
        echo '<div class="' . htmlspecialchars($class) . '" style="' . htmlspecialchars($style) . ';background:none;padding:0;overflow:hidden;">';
        echo '<img src="' . $baseUrl . htmlspecialchars($img) . '" alt="' . htmlspecialchars($book['judul']) . '" style="width:100%;height:100%;object-fit:cover;display:block;">';
        echo '</div>';
    } else {
        // Gradient fallback
        $bg = 'background:linear-gradient(135deg,' . htmlspecialchars($book['color1']) . ',' . htmlspecialchars($book['color2']) . ');color:' . htmlspecialchars($book['color3']) . ';';
        echo '<div class="' . htmlspecialchars($class) . '" style="' . $bg . htmlspecialchars($style) . '">';
        if ($showText) {
            echo '<span style="' . htmlspecialchars($textStyle) . '">' . htmlspecialchars(substr($book['judul'], 0, 35)) . '</span>';
        }
        echo '</div>';
    }
}

/**
 * Return an img src string or empty string for direct use in HTML.
 */
function bookCoverSrc(array $book): string {
    $img = $book['cover_image'] ?? '';
    $baseUrl = '/libfisip/uploads/covers/';
    if ($img && file_exists($_SERVER['DOCUMENT_ROOT'] . $baseUrl . $img)) {
        return $baseUrl . htmlspecialchars($img);
    }
    return '';
}

/**
 * Handle cover image upload. Returns filename on success, '' on skip, throws on error.
 */
function handleCoverUpload(string $fileKey, ?string $oldFile = null): string {
    if (empty($_FILES[$fileKey]['tmp_name'])) return ''; // No file uploaded

    $file = $_FILES[$fileKey];
    $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $extMap  = ['jpg' => true, 'jpeg' => true, 'png' => true];

    if (!in_array($file['type'], $allowed) || !isset($extMap[$ext])) {
        throw new RuntimeException('Format file tidak valid. Gunakan JPG, JPEG, atau PNG.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Ukuran file terlalu besar. Maksimal 5MB.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal. Kode error: ' . $file['error']);
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/libfisip/uploads/covers/';
    $filename  = 'cover_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest      = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Gagal menyimpan file. Periksa izin folder uploads/covers/.');
    }

    // Delete old file if replacing
    if ($oldFile && file_exists($uploadDir . $oldFile)) {
        @unlink($uploadDir . $oldFile);
    }

    return $filename;
}
?>
