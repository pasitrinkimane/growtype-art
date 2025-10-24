<?php

function growtype_art_image_get_alternative_format($url, $target_type = 'webp', $convert = false)
{
    // Supported types
    $supported_types = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($target_type, $supported_types)) {
        return $url;
    }

    $final_url = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '.' . $target_type, $url);
    $parsed_final_path = parse_url($final_url, PHP_URL_PATH);
    $final_path = $_SERVER['DOCUMENT_ROOT'] . '/web/' . $parsed_final_path;

    if (file_exists($final_path)) {
        return $final_url;
    }

    if (!$convert) {
        return $url;
    }

    $parsed_original_path = parse_url($url, PHP_URL_PATH);
    $original_path = $_SERVER['DOCUMENT_ROOT'] . '/web/' . $parsed_original_path;
    $target_path = preg_replace('/\.[^.]+$/', '.' . $target_type, $final_path);
    $source_ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));

    $info = getimagesize($original_path);

    if (!$info) {
        return $url;
    }

    // Load image
    $mime = $info['mime']; // e.g., 'image/png', 'image/jpeg', 'image/webp'
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($original_path);
            break;
        case 'image/png':
            $image = imagecreatefrompng($original_path);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($original_path);
            break;
        default:
            return $url; // Unsupported format
    }

    if (!$image) {
        return $url;
    }

    // Convert and save new file (without touching the original)
    switch ($target_type) {
        case 'jpg':
        case 'jpeg':
            imagejpeg($image, $target_path, 90);
            break;
        case 'png':
            imagepng($image, $target_path);
            break;
        case 'webp':
            imagewebp($image, $target_path, 90);
            break;
        default:
            imagedestroy($image);
            return $url;
    }

    imagedestroy($image);

    return $final_url;
}

function is_image_larger_than($imagePath, $maxWidth = 768, $maxHeight = 1024)
{
    // Get the image dimensions
    list($width, $height) = getimagesize($imagePath);

    // Check if width or height exceeds the given dimensions
    if ($width > $maxWidth || $height > $maxHeight) {
        return true; // The image is larger
    }
    return false; // The image is within the size limit
}

function crop_image($srcPath, $destPath, $cropWidth, $cropHeight, $xOffset = 0, $yOffset = 0)
{
    // Get the image type and size
    list($width, $height, $type) = getimagesize($srcPath);

    // Create a resource based on the image type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $srcImage = imagecreatefromjpeg($srcPath);
            break;
        case IMAGETYPE_PNG:
            $srcImage = imagecreatefrompng($srcPath);
            break;
        case IMAGETYPE_GIF:
            $srcImage = imagecreatefromgif($srcPath);
            break;
        case IMAGETYPE_WEBP:
            $srcImage = imagecreatefromwebp($srcPath);
            break;
        default:
            throw new Exception("Unsupported image type");
    }

    $xOffset = !empty($xOffset) ? $xOffset : max(0, ($width - $cropWidth) / 2);
    $yOffset = !empty($yOffset) ? $yOffset : max(0, ($height - $cropHeight) / 2);

    // Create a blank true color image for the cropped output
    $croppedImage = imagecreatetruecolor($cropWidth, $cropHeight);

    // Enable alpha blending and save alpha for PNG and WebP
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($croppedImage, false);
        imagesavealpha($croppedImage, true);
    }

    // Crop the image
    imagecopyresampled(
        $croppedImage, // Destination image
        $srcImage,     // Source image
        0, 0,          // Destination x, y
        $xOffset, $yOffset, // Source x, y
        $cropWidth, $cropHeight, // Destination width, height
        $cropWidth, $cropHeight  // Source width, height
    );

    // Save the cropped image
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($croppedImage, $destPath);
            break;
        case IMAGETYPE_PNG:
            imagepng($croppedImage, $destPath);
            break;
        case IMAGETYPE_GIF:
            imagegif($croppedImage, $destPath);
            break;
        case IMAGETYPE_WEBP:
            imagewebp($croppedImage, $destPath);
            break;
    }

    // Free memory
    imagedestroy($srcImage);
    imagedestroy($croppedImage);

    return true;
}
