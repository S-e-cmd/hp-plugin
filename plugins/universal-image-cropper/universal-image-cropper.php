<?php
/**
 * Plugin Name: 共通画像トリミング
 * Description: WordPressの画像選択画面に「トリミングして使用」を追加し、加工画像を別ファイルとして保存します。
 * Version: 1.6.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

final class Universal_Image_Cropper {
    const VERSION = '1.6.0';
    const NONCE = 'uic_crop_image';

    public static function init() {
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('wp_ajax_uic_crop_image', [__CLASS__, 'ajax_crop_image']);
    }

    public static function admin_assets() {
        if (!current_user_can('upload_files')) return;

        wp_enqueue_media();
        wp_enqueue_style(
            'uic-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            self::VERSION
        );
        wp_enqueue_script(
            'uic-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery', 'media-editor', 'media-views'],
            self::VERSION,
            true
        );
        wp_localize_script('uic-admin', 'UIC_DATA', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'maxDimension' => 5000,
        ]);
    }

    public static function ajax_crop_image() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => '画像を加工する権限がありません。'], 403);
        }

        $attachment_id = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;
        $crop_x = isset($_POST['crop_x']) ? (float)$_POST['crop_x'] : 0;
        $crop_y = isset($_POST['crop_y']) ? (float)$_POST['crop_y'] : 0;
        $crop_w = isset($_POST['crop_w']) ? (float)$_POST['crop_w'] : 0;
        $crop_h = isset($_POST['crop_h']) ? (float)$_POST['crop_h'] : 0;
        $dest_w = isset($_POST['dest_w']) ? absint($_POST['dest_w']) : 0;
        $dest_h = isset($_POST['dest_h']) ? absint($_POST['dest_h']) : 0;
        $quality = isset($_POST['quality']) ? absint($_POST['quality']) : 88;
        $suffix = sanitize_file_name(wp_unslash($_POST['suffix'] ?? 'cropped'));

        if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
            wp_send_json_error(['message' => '元画像が見つかりません。'], 400);
        }
        if ($crop_w < 1 || $crop_h < 1 || $dest_w < 1 || $dest_h < 1) {
            wp_send_json_error(['message' => '切り抜き範囲または出力サイズが不正です。'], 400);
        }
        if ($dest_w > 5000 || $dest_h > 5000) {
            wp_send_json_error(['message' => '出力サイズは5000px以下にしてください。'], 400);
        }
        $quality = max(40, min(100, $quality));

        $file = get_attached_file($attachment_id);
        if (!$file || !is_readable($file)) {
            wp_send_json_error(['message' => '元画像ファイルを読み込めません。'], 400);
        }

        $meta = wp_get_attachment_metadata($attachment_id);
        $src_w = isset($meta['width']) ? (int)$meta['width'] : 0;
        $src_h = isset($meta['height']) ? (int)$meta['height'] : 0;
        if (!$src_w || !$src_h) {
            $size = @getimagesize($file);
            $src_w = (int)($size[0] ?? 0);
            $src_h = (int)($size[1] ?? 0);
        }
        if (!$src_w || !$src_h) {
            wp_send_json_error(['message' => '元画像のサイズを取得できません。'], 400);
        }

        $crop_x = max(0, min($src_w - 1, $crop_x));
        $crop_y = max(0, min($src_h - 1, $crop_y));
        $crop_w = max(1, min($src_w - $crop_x, $crop_w));
        $crop_h = max(1, min($src_h - $crop_y, $crop_h));

        $editor = wp_get_image_editor($file);
        if (is_wp_error($editor)) {
            wp_send_json_error(['message' => $editor->get_error_message()], 500);
        }
        $editor->set_quality($quality);
        $result = $editor->crop(
            (int)round($crop_x),
            (int)round($crop_y),
            (int)round($crop_w),
            (int)round($crop_h),
            $dest_w,
            $dest_h,
            false
        );
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 500);
        }

        $pathinfo = pathinfo($file);
        $base = sanitize_file_name($pathinfo['filename']);
        $ext = strtolower($pathinfo['extension'] ?? 'jpg');
        $suffix = $suffix ?: 'cropped';
        $new_name = $base . '-' . $suffix . '-' . $dest_w . 'x' . $dest_h . '-' . wp_date('Ymd-His') . '.' . $ext;
        $new_path = trailingslashit($pathinfo['dirname']) . wp_unique_filename($pathinfo['dirname'], $new_name);

        $saved = $editor->save($new_path);
        if (is_wp_error($saved)) {
            wp_send_json_error(['message' => $saved->get_error_message()], 500);
        }

        $filetype = wp_check_filetype($saved['path']);
        $title = get_the_title($attachment_id);
        $new_attachment_id = wp_insert_attachment([
            'post_mime_type' => $filetype['type'] ?: ($saved['mime-type'] ?? 'image/jpeg'),
            'post_title' => $title . '（調整済み）',
            'post_content' => '',
            'post_status' => 'inherit',
        ], $saved['path'], 0, true);

        if (is_wp_error($new_attachment_id)) {
            @unlink($saved['path']);
            wp_send_json_error(['message' => $new_attachment_id->get_error_message()], 500);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        $new_meta = wp_generate_attachment_metadata($new_attachment_id, $saved['path']);
        wp_update_attachment_metadata($new_attachment_id, $new_meta);

        $alt = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
        if ($alt !== '') update_post_meta($new_attachment_id, '_wp_attachment_image_alt', $alt);
        $caption = wp_get_attachment_caption($attachment_id);
        if ($caption) wp_update_post(['ID' => $new_attachment_id, 'post_excerpt' => $caption]);

        $attachment = wp_prepare_attachment_for_js($new_attachment_id);
        if (!$attachment) {
            wp_send_json_error(['message' => '加工画像は保存されましたが、選択画面へ返せませんでした。'], 500);
        }

        wp_send_json_success([
            'message' => '加工画像を新しいファイルとして保存しました。',
            'attachment' => $attachment,
        ]);
    }
}

Universal_Image_Cropper::init();
