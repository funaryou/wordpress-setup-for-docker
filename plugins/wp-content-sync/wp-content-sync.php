<?php
/*
Plugin Name: WP Content Sync
Description: WordPressのコンテンツを完全に双方向同期します
Version: 2.0
Author: funa
*/

// 基本的なフック
add_action('save_post', 'save_post_to_file', 10, 3);
add_action('admin_init', 'check_file_changes');
add_action('wp_insert_comment', 'save_comment_to_file');
add_action('edit_comment', 'save_comment_to_file');
add_action('create_term', 'save_taxonomy_to_file', 10, 3);
add_action('edit_term', 'save_taxonomy_to_file', 10, 3);
add_action('update_post_meta', 'save_meta_to_file', 10, 4);
add_action('add_post_meta', 'save_meta_to_file', 10, 4);

function save_post_to_file($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (defined('UPDATING_FROM_FILE') && UPDATING_FROM_FILE) return;

    // 投稿タイプに応じたディレクトリを選択
    $dir = get_post_type_directory($post->post_type);
    $directory = WP_CONTENT_DIR . '/' . $dir;
    ensure_directory($directory);

    // 投稿の基本情報を保存
    save_post_base_info($post, $directory);
    
    // カスタムフィールドを保存
    save_post_meta($post_id, $directory);
    
    // タクソノミーを保存
    save_post_taxonomies($post_id, $directory);
    
    // コメントを保存
    save_post_comments($post_id, $directory);
}

function save_post_base_info($post, $directory) {
    $filename = $post->post_name ? $post->post_name : sanitize_title($post->post_title);
    $file_path = $directory . '/' . $filename . '.php';

    $content = array(
        'post_info' => array(
            'ID' => $post->ID,
            'title' => $post->post_title,
            'date' => $post->post_date,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'modified' => date('Y-m-d H:i:s')
        ),
        'content' => $post->post_content
    );

    file_put_contents($file_path, '<?php return ' . var_export($content, true) . ';');
    update_post_meta($post->ID, '_file_last_modified', filemtime($file_path));
}

function save_post_meta($post_id, $directory) {
    $meta = get_post_meta($post_id);
    $meta_file = $directory . '/meta_' . $post_id . '.php';
    file_put_contents($meta_file, '<?php return ' . var_export($meta, true) . ';');
}

function save_post_taxonomies($post_id, $directory) {
    $taxonomies = get_object_taxonomies(get_post_type($post_id));
    $tax_data = array();
    
    foreach ($taxonomies as $tax) {
        $terms = wp_get_object_terms($post_id, $tax);
        if (!is_wp_error($terms)) {
            $tax_data[$tax] = $terms;
        }
    }
    
    $tax_file = $directory . '/tax_' . $post_id . '.php';
    file_put_contents($tax_file, '<?php return ' . var_export($tax_data, true) . ';');
}

function save_post_comments($post_id, $directory) {
    $comments = get_comments(array('post_id' => $post_id));
    $comments_file = $directory . '/comments_' . $post_id . '.php';
    file_put_contents($comments_file, '<?php return ' . var_export($comments, true) . ';');
}

function check_file_changes() {
    $post_types = get_post_types(array(), 'names');
    
    foreach ($post_types as $post_type) {
        $dir = get_post_type_directory($post_type);
        $directory = WP_CONTENT_DIR . '/' . $dir;
        
        if (!file_exists($directory)) continue;
        
        check_post_files($directory, $post_type);
        check_meta_files($directory);
        check_taxonomy_files($directory);
        check_comment_files($directory);
    }
}

function check_post_files($directory, $post_type) {
    $files = glob($directory . '/*.php');
    
    foreach ($files as $file) {
        if (strpos($file, 'meta_') === 0 || 
            strpos($file, 'tax_') === 0 || 
            strpos($file, 'comments_') === 0) {
            continue;
        }
        
        $content = include($file);
        if (!is_array($content)) continue;
        
        $post = get_post($content['post_info']['ID']);
        if ($post && $post->post_modified < $content['post_info']['modified']) {
            define('UPDATING_FROM_FILE', true);
            
            wp_update_post(array(
                'ID' => $post->ID,
                'post_title' => $content['post_info']['title'],
                'post_content' => $content['content'],
                'post_status' => $content['post_info']['status']
            ));
        }
    }
}

// ヘルパー関数
function get_post_type_directory($post_type) {
    switch ($post_type) {
        case 'post':
            return 'posts';
        case 'page':
            return 'pages';
        default:
            return 'custom-posts/' . $post_type;
    }
}

function ensure_directory($directory) {
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
}

// プラグイン有効化時の処理
register_activation_hook(__FILE__, 'post_to_file_activate');

function post_to_file_activate() {
    // ディレクトリ構造の作成
    $base_dirs = array(
        'posts',
        'pages',
        'custom-posts',
        'taxonomies',
        'comments',
        'menus',
        'meta'
    );
    
    foreach ($base_dirs as $dir) {
        ensure_directory(WP_CONTENT_DIR . '/' . $dir);
    }

    // 既存のコンテンツをエクスポート
    $posts = get_posts(array(
        'post_type' => get_post_types(),
        'numberposts' => -1
    ));

    foreach ($posts as $post) {
        save_post_to_file($post->ID, $post, false);
    }
} 