<?php
if (!function_exists('get_user_avatar')) {
function get_user_avatar($user, $classes = '', $size = 'md') {
    $sizes = [
        'sm' => ['width' => '32px', 'height' => '32px', 'font-size' => '0.875rem'],
        'md' => ['width' => '48px', 'height' => '48px', 'font-size' => '1rem'],
        'lg' => ['width' => '64px', 'height' => '64px', 'font-size' => '1.5rem'],
        'xl' => ['width' => '96px', 'height' => '96px', 'font-size' => '2rem']
    ];
    
    $style = $sizes[$size] ?? $sizes['md'];
    $styleStr = sprintf('width: %s; height: %s; font-size: %s;', 
        $style['width'], $style['height'], $style['font-size']);
    
    // Check if user has a profile picture
    if (!empty($user['profile_picture']) && 
        file_exists(__DIR__ . '/../assets/uploads/profile_pictures/' . $user['profile_picture'])) {
        return sprintf(
            '<div class="user-avatar %s" style="%s">
                <img src="assets/uploads/profile_pictures/%s" alt="%s" class="avatar-image">
            </div>',
            htmlspecialchars($classes),
            $styleStr,
            htmlspecialchars($user['profile_picture']),
            htmlspecialchars($user['name'] ?? $user['email'])
        );
    }
    
    // Fallback to initials with gradient background
    $initial = strtoupper(substr($user['name'] ?? $user['email'], 0, 1));
    $gradient = avatar_style($user['email'] ?? $user['id']);
    
    return sprintf(
        '<div class="user-avatar %s" style="%s background: %s" title="%s">%s</div>',
        htmlspecialchars($classes),
        $styleStr,
        $gradient,
        htmlspecialchars($user['name'] ?? $user['email']),
        htmlspecialchars($initial)
    );
}
}

if (!function_exists('avatar_style')) {
function avatar_style($seed) {
    $hash = md5($seed);
    $h = hexdec(substr($hash, 0, 6)) % 360;
    $h2 = ($h + 30) % 360;
    return "linear-gradient(135deg, hsl($h,70%,52%), hsl($h2,65%,45%))";
}
}