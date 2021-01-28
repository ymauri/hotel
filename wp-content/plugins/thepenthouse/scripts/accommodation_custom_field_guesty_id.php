<?php

function add_guesty_id_meta_box()
{
    add_meta_box(
        'guesty_id_meta_box', // $id
        'Guesty Id', // $title
        'show_guesty_id_meta_box', // $callback
        'mphb_room', // $screen
        'normal', // $context
        'high' // $priority
    );
}
add_action('add_meta_boxes', 'add_guesty_id_meta_box');

function show_guesty_id_meta_box()
{
    global $post;
    $meta = get_post_meta($post->ID, 'guesty_id', true);
    echo '<textarea rows="1" style="width:100%" name="guesty_id" id="guesty_id">' . $meta . '</textarea>';
    echo '<p>Listing ID on Guesty (Ex: 58a5dffa3798420400c8e57e). <a target="_blank" href="https://app.guesty.com/listings">Click here for view all.</a></p>';
    // echo '<input type="hidden" name="guesty_id_nonce" value="'.wp_create_nonce( basename(__FILE__) ).'">';
}

function save_guesty_id_meta($post_id)
{
    // verify nonce
    // if (!wp_verify_nonce($_POST['guesty_id_nonce'], basename(__FILE__))) {
    //     return $post_id;
    // }
    // check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }
    // check permissions
    if (!empty($_POST['post_type']) && 'page' === $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return $post_id;
        } elseif (!current_user_can('edit_post', $post_id)) {
            return $post_id;
        }
    }

    $old = get_post_meta($post_id, 'guesty_id', true);
    $new = $_POST['guesty_id'];

    if ($new && $new !== $old) {
        update_post_meta($post_id, 'guesty_id', $new);
    } elseif ('' === $new && $old) {
        delete_post_meta($post_id, 'guesty_id', $old);
    }
}

add_action('save_post', 'save_guesty_id_meta');
