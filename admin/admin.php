<?php

if (!defined('ABSPATH')) exit;

class AsgarosForumAdmin {
    var $saved = false;
    var $error = false;
    // TODO: Remove globals
    private $asgarosforum = null;

    function __construct($object) {
        $this->asgarosforum = $object;

        add_action('wp_loaded', array($this, 'save_settings'));
        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // User profile options.
        add_action('edit_user_profile', array($this, 'user_profile_fields'));
        add_action('show_user_profile', array($this, 'user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'user_profile_fields_update'));
        add_action('personal_options_update', array($this, 'user_profile_fields_update'));
    }

    function user_profile_fields($user) {
        // TODO: get_the_author_meta can be removed. Use get_user_meta instead.
        global $asgarosforum;
        $output = '';

        // Show settings only when current user is admin ...
        if (current_user_can('manage_options')) {
            // ... and he edits a non-admin user.
            if (!user_can($user->ID, 'manage_options')) {
                $role = $asgarosforum->permissions->get_forum_role($user->ID);

                $output .= '<tr>';
                $output .= '<th><label for="asgarosforum_role">'.__('Forum Role', 'asgaros-forum').'</label></th>';
                $output .= '<td>';

                $output .= '<select name="asgarosforum_role" id="asgarosforum_role">';
                $output .= '<option value="normal" '.selected($role, 'normal', false).'>'.__('Normal', 'asgaros-forum').'</option>';
                $output .= '<option value="moderator" '.selected($role, 'moderator', false).'>'.__('Moderator', 'asgaros-forum').'</option>';
                $output .= '<option value="administrator" '.selected($role, 'administrator', false).'>'.__('Administrator', 'asgaros-forum').'</option>';
                $output .= '<option value="banned" '.selected($role, 'banned', false).'>'.__('Banned', 'asgaros-forum').'</option>';
                $output .= '</select>';

                $output .= '</td>';
                $output .= '</tr>';
            }

            $output .= AsgarosForumUserGroups::showUserProfileFields($user->ID);
        }

        if ($asgarosforum->options['enable_mentioning']) {
            $output .= '<tr>';
            $output .= '<th><label for="asgarosforum_mention_notify">'.__('Notify me when I get mentioned', 'asgaros-forum').'</label></th>';
            $output .= '<td><input type="checkbox" name="asgarosforum_mention_notify" id="asgarosforum_mention_notify" value="1" '.checked($asgarosforum->mentioning->user_wants_notification($user->ID), true, false).'></td>';
            $output .= '</tr>';
        }

        if ($asgarosforum->options['allow_signatures']) {
            // Ensure that the user has permission to use a signature.
            if ($asgarosforum->permissions->can_use_signature($user->ID)) {
                $output .= '<tr>';
                $output .= '<th><label for="asgarosforum_signature">'.__('Signature', 'asgaros-forum').'</label></th>';
                $output .= '<td>';
                $output .= '<textarea rows="5" cols="30" name="asgarosforum_signature" id="asgarosforum_signature">'.get_the_author_meta('asgarosforum_signature', $user->ID).'</textarea>';

                // Show info about allowed HTML tags.
                if ($asgarosforum->options['signatures_html_allowed']) {
                    $output .= '<p class="description">';
                    $output .= __('You can use the following HTML tags in signatures:', 'asgaros-forum');
                    $output .= '&nbsp;<code>'.esc_html($asgarosforum->options['signatures_html_tags']).'</code>';
                    $output .= '</p>';
                } else {
                    $output .= '<p class="description">'.__('HTML tags are not allowed in signatures.', 'asgaros-forum').'</p>';
                }

                $output .= '</td>';
                $output .= '</tr>';
            }
        }

        if (!empty($output)) {
            echo '<h2>'.__('Forum', 'asgaros-forum').'</h2>';
            echo '<table class="form-table">';
            echo $output;
            echo '</table>';
        }
    }

    function user_profile_fields_update($user_id) {
        global $asgarosforum;
        $user_id = absint($user_id);

        if (current_user_can('manage_options')) {
            if (!user_can($user_id, 'manage_options')) {
                if (isset($_POST['asgarosforum_role'])) {
                    $asgarosforum->permissions->set_forum_role($user_id, $_POST['asgarosforum_role']);
                }
            }

            AsgarosForumUserGroups::updateUserProfileFields($user_id);
        }

        if ($asgarosforum->options['enable_mentioning']) {
            if (isset($_POST['asgarosforum_mention_notify'])) {
                update_user_meta($user_id, 'asgarosforum_mention_notify', 'yes');
            } else {
                update_user_meta($user_id, 'asgarosforum_mention_notify', 'no');
            }
        }

        if ($asgarosforum->options['allow_signatures']) {
            // Ensure that the user has permission to use a signature.
            if ($asgarosforum->permissions->can_use_signature($user_id)) {
                if (isset($_POST['asgarosforum_signature'])) {
                    if ($asgarosforum->options['signatures_html_allowed']) {
                        update_user_meta($user_id, 'asgarosforum_signature', trim(wp_kses_post(strip_tags($_POST['asgarosforum_signature'], $asgarosforum->options['signatures_html_tags']))));
                    } else {
                        update_user_meta($user_id, 'asgarosforum_signature', trim(wp_kses_post(strip_tags($_POST['asgarosforum_signature']))));
                    }
                } else {
                    delete_user_meta($user_id, 'asgarosforum_signature');
                }
            }
        }
    }

    // Add all required pages to the menu.
    function add_admin_pages() {
        if ($this->asgarosforum->permissions->isAdministrator('current')) {
            add_menu_page(__('Forum', 'asgaros-forum'), __('Forum', 'asgaros-forum'), 'read', 'asgarosforum-structure', array($this, 'structure_page'), 'none');
            add_submenu_page('asgarosforum-structure', __('Structure', 'asgaros-forum'), __('Structure', 'asgaros-forum'), 'read', 'asgarosforum-structure', array($this, 'structure_page'));
            add_submenu_page('asgarosforum-structure', __('Appearance', 'asgaros-forum'), __('Appearance', 'asgaros-forum'), 'read', 'asgarosforum-appearance', array($this, 'appearance_page'));
            add_submenu_page('asgarosforum-structure', __('Usergroups', 'asgaros-forum'), __('Usergroups', 'asgaros-forum'), 'read', 'asgarosforum-usergroups', array($this, 'usergroups_page'));

            if ($this->asgarosforum->options['enable_ads']) {
                add_submenu_page('asgarosforum-structure', __('Ads', 'asgaros-forum'), __('Ads', 'asgaros-forum'), 'read', 'asgarosforum-ads', array($this, 'ads_page'));
            }

            do_action('asgarosforum_add_admin_submenu_page');

            add_submenu_page('asgarosforum-structure', __('Settings', 'asgaros-forum'), __('Settings', 'asgaros-forum'), 'read', 'asgarosforum-options', array($this, 'options_page'));
        }
    }

    function options_page() {
        global $asgarosforum;
        require('views/options.php');
    }

    function structure_page() {
        global $asgarosforum;

        require('views/structure.php');
    }

    function appearance_page() {
        require('views/appearance.php');
    }

    function usergroups_page() {
        require('views/usergroups.php');
    }

    function ads_page() {
        require('views/ads.php');
    }

    function enqueue_admin_scripts($hook) {
        global $asgarosforum;

        wp_enqueue_style('asgarosforum-fontawesome-css', $asgarosforum->plugin_url.'libs/fontawesome/css/all.min.css', array(), $asgarosforum->version);
        wp_enqueue_style('asgarosforum-admin-css', $asgarosforum->plugin_url.'admin/css/admin.css', array(), $asgarosforum->version);

        if (strstr($hook, 'asgarosforum') !== false) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('asgarosforum-admin-js', $asgarosforum->plugin_url.'admin/js/admin.js', array('wp-color-picker'), $asgarosforum->version, true);
        }
    }

    function save_settings() {
        // Only save changes when the user is an forum/site administrator.
        if ($this->asgarosforum->permissions->isAdministrator('current')) {
            if (isset($_POST['af_options_submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_options');

                $this->save_options();
            } else if (isset($_POST['af_appearance_submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_appearance');

                $this->save_appearance();
            } else if (isset($_POST['af-create-edit-forum-submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_forum');

                $this->save_forum();
            } else if (isset($_POST['asgaros-forum-delete-forum'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_delete_forum');

                if (!empty($_POST['forum-id']) && is_numeric($_POST['forum-id']) && !empty($_POST['forum-category']) && is_numeric($_POST['forum-category'])) {
                    $this->delete_forum($_POST['forum-id'], $_POST['forum-category']);
                }
            } else if (isset($_POST['af-create-edit-category-submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_category');

                $this->save_category();
            } else if (isset($_POST['asgaros-forum-delete-category'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_delete_category');

                if (!empty($_POST['category-id']) && is_numeric($_POST['category-id'])) {
                    $this->delete_category($_POST['category-id']);
                }
            } else if (isset($_POST['af-create-edit-usergroup-category-submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_usergroup_category');

                $saveStatus = AsgarosForumUserGroups::saveUserGroupCategory();

                if (is_wp_error($saveStatus)) {
                    $this->error = $saveStatus->get_error_message();
                } else {
                    $this->saved = $saveStatus;
                }
            } else if (isset($_POST['af-create-edit-usergroup-submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_usergroup');

                $saveStatus = AsgarosForumUserGroups::saveUserGroup();

                if (is_wp_error($saveStatus)) {
                    $this->error = $saveStatus->get_error_message();
                } else {
                    $this->saved = $saveStatus;
                }
            } else if (isset($_POST['asgaros-forum-delete-usergroup'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_delete_usergroup');

                if (!empty($_POST['usergroup-id']) && is_numeric($_POST['usergroup-id'])) {
                    AsgarosForumUserGroups::deleteUserGroup($_POST['usergroup-id']);
                }
            } else if (isset($_POST['asgaros-forum-delete-usergroup-category'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_delete_usergroup_category');

                if (!empty($_POST['usergroup-category-id']) && is_numeric($_POST['usergroup-category-id'])) {
                    AsgarosForumUserGroups::deleteUserGroupCategory($_POST['usergroup-category-id']);
                }
            } else if (isset($_POST['af-create-edit-ad-submit'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_save_ad');

                $ad_id          = $_POST['ad_id'];
                $ad_name        = trim($_POST['ad_name']);
                $ad_code        = trim($_POST['ad_code']);
                $ad_active      = isset($_POST['ad_active']) ? 1 : 0;
                $ad_locations   = isset($_POST['ad_locations']) ? implode(',', $_POST['ad_locations']) : '';

                $this->asgarosforum->ads->save_ad($ad_id, $ad_name, $ad_code, $ad_active, $ad_locations);
                $this->saved = true;
            } else if (isset($_POST['asgaros-forum-delete-ad'])) {
                // Verify nonce first.
                check_admin_referer('asgaros_forum_delete_ad');

                if (!empty($_POST['ad_id']) && is_numeric($_POST['ad_id'])) {
                    $this->asgarosforum->ads->delete_ad($_POST['ad_id']);
                    $this->saved = true;
                }
            }
        }
    }

    /* OPTIONS */
    function save_options() {
        global $asgarosforum;
        $saved_ops = array();

        foreach ($asgarosforum->options_default as $k => $v) {
            if (isset($_POST[$k])) {
                if ($k === 'uploads_maximum_number' || $k === 'uploads_maximum_size') {
                    $saved_ops[$k] = ((int)$_POST[$k] >= 0) ? (int)$_POST[$k] : $v;
                } else if (is_numeric($v)) {
                    $saved_ops[$k] = ((int)$_POST[$k] > 0) ? (int)$_POST[$k] : $v;
                } else if (is_bool($v)) {
                    $saved_ops[$k] = (bool)$_POST[$k];
                } else if ($k === 'allowed_filetypes') {
                    $tmp = stripslashes(strtolower(trim($_POST[$k])));
                    $saved_ops[$k] = (!empty($tmp)) ? $tmp : $v;
                } else {
                    $tmp = stripslashes(trim($_POST[$k]));
                    $saved_ops[$k] = (!empty($tmp)) ? $tmp : $v;
                }
            } else {
                if (is_bool($v)) {
                    $saved_ops[$k] = false;
                } else {
                    $saved_ops[$k] = $v;
                }
            }
        }

        $asgarosforum->saveOptions($saved_ops);
        $this->saved = true;
    }

    function save_appearance() {
        global $asgarosforum;
        $saved_ops = array();

        foreach ($asgarosforum->appearance->options_default as $k => $v) {
            if (isset($_POST[$k])) {
                $tmp = stripslashes(trim($_POST[$k]));
                $saved_ops[$k] = (!empty($tmp)) ? $tmp : $v;
            } else {
                $saved_ops[$k] = $v;
            }
        }

        $asgarosforum->appearance->save_options($saved_ops);
        $this->saved = true;
    }

    /* STRUCTURE */
    function save_category() {
        global $asgarosforum;
        $category_id        = $_POST['category_id'];
        $category_name      = trim($_POST['category_name']);
        $category_access    = trim($_POST['category_access']);
        $category_order     = (is_numeric($_POST['category_order'])) ? $_POST['category_order'] : 1;

        if (!empty($category_name)) {
            if ($category_id === 'new') {
                $newTerm = wp_insert_term($category_name, 'asgarosforum-category');

                // Return possible error.
                if (is_wp_error($newTerm)) {
                    $this->error = $newTerm->get_error_message();
                    return;
                }

                $category_id = $newTerm['term_id'];
            } else {
                wp_update_term($category_id, 'asgarosforum-category', array('name' => $category_name));
            }

            update_term_meta($category_id, 'category_access', $category_access);
            update_term_meta($category_id, 'order', $category_order);
            AsgarosForumUserGroups::saveUserGroupsOfForumCategory($category_id);

            $this->saved = true;
        }
    }

    function save_forum() {
        global $asgarosforum;

        // ID of the forum.
        $forum_id           = $_POST['forum_id'];

        // Determine parent IDs.
        $parent_ids          = explode('_', $_POST['forum_parent']);
        $forum_category     = $parent_ids[0];
        $forum_parent_forum = $parent_ids[1];

        // Additional data.
        $forum_name         = trim($_POST['forum_name']);
        $forum_description  = trim($_POST['forum_description']);
        $forum_icon         = trim($_POST['forum_icon']);
        $forum_icon         = (empty($forum_icon)) ? 'fas fa-comments' : $forum_icon;
        $forum_closed       = (isset($_POST['forum_closed'])) ? 1 : 0;
        $forum_approval     = (isset($_POST['forum_approval'])) ? 1 : 0;
        $forum_order        = (is_numeric($_POST['forum_order'])) ? $_POST['forum_order'] : 0;

        if (!empty($forum_name)) {
            if ($forum_id === 'new') {
                $asgarosforum->content->insert_forum($forum_category, $forum_name, $forum_description, $forum_parent_forum, $forum_icon, $forum_order, $forum_closed, $forum_approval);
            } else {
                // Update forum.
                $asgarosforum->db->update(
                    $asgarosforum->tables->forums,
                    array('name' => $forum_name, 'description' => $forum_description, 'icon' => $forum_icon, 'sort' => $forum_order, 'closed' => $forum_closed, 'approval' => $forum_approval, 'parent_id' => $forum_category, 'parent_forum' => $forum_parent_forum),
                    array('id' => $forum_id),
                    array('%s', '%s', '%s', '%d', '%d', '%d', '%d'),
                    array('%d')
                );

                // Update category ids of sub-forums in case the forum got moved.
                $asgarosforum->db->update(
                    $asgarosforum->tables->forums,
                    array('parent_id' => $forum_category),
                    array('parent_forum' => $forum_id),
                    array('%d'),
                    array('%d')
                );

                // Approve all unapproved topics in a forum if the approval-function is off.
                if ($forum_approval === 0) {
                    // Get all unapproved topics from this forum.
                    $unapproved_topics = $asgarosforum->approval->get_unapproved_topics($forum_id);

                    // Approve those topics if found.
                    if (!empty($unapproved_topics)) {
                        foreach ($unapproved_topics as $topic) {
                            $asgarosforum->approval->approve_topic($topic->id);
                        }
                    }
                }
            }

            $this->saved = true;
        }
    }

    function delete_category($categoryID) {
        global $asgarosforum;

        $forums = $asgarosforum->db->get_col("SELECT id FROM {$asgarosforum->tables->forums} WHERE parent_id = {$categoryID};");

        if (!empty($forums)) {
            foreach ($forums as $forum) {
                $this->delete_forum($forum, $categoryID);
            }
        }

        wp_delete_term($categoryID, 'asgarosforum-category');
    }

    function delete_forum($forum_id, $category_id) {
        global $asgarosforum;

        // Delete all subforums first
        $subforums = $asgarosforum->get_forums($category_id, $forum_id);

        if (count($subforums) > 0) {
            foreach ($subforums as $subforum) {
                $this->delete_forum($subforum->id, $category_id);
            }
        }

        // Delete all topics.
        $topics = $asgarosforum->db->get_col("SELECT id FROM {$asgarosforum->tables->topics} WHERE parent_id = {$forum_id};");

        if (!empty($topics)) {
            foreach ($topics as $topic) {
                $asgarosforum->delete_topic($topic, true);
            }
        }

        // Delete subscriptions for this forum.
        $asgarosforum->notifications->remove_all_forum_subscriptions($forum_id);

        // Last but not least delete the forum
        $asgarosforum->db->delete($asgarosforum->tables->forums, array('id' => $forum_id), array('%d'));

        $this->saved = true;
    }

    /* USERGROUPS */
    function render_admin_header($title, $titleUpdated) {
        global $asgarosforum;

        // Workaround to ensure that admin-notices are shown outside of our panel.
        echo '<h1 id="asgaros-panel-notice-area"></h1>';

        echo '<div id="asgaros-panel">';
            echo '<div class="header-panel">';
                echo '<div class="sub-panel-left">';
                    echo '<img src="'.$asgarosforum->plugin_url.'admin/images/logo.png">';
                echo '</div>';
                echo '<div class="sub-panel-left">';
                    echo '<h1>'.$title.'</h1>';
                echo '</div>';
                echo '<div class="sub-panel-right">';
                    echo '<a href="https://www.asgaros.de/support/" target="_blank">';
                        echo '<span class="asgaros-panel-icon fas fa-user"></span>';
                        echo __('Official Support Forum', 'asgaros-forum');
                    echo '</a>';
                    echo '&bull;';
                    echo '<a href="https://www.asgaros.de/docs/" target="_blank">';
                        echo '<span class="asgaros-panel-icon fas fa-book"></span>';
                        echo __('Documentation', 'asgaros-forum');
                    echo '</a>';
                    echo '&bull;';
                    echo '<a href="https://www.paypal.me/asgaros" target="_blank">';
                        echo '<span class="asgaros-panel-icon donate-icon fas fa-heart"></span>';
                        echo __('Donate', 'asgaros-forum');
                    echo '</a>';
                echo '</div>';
                echo '<div class="clear"></div>';
            echo '</div>';

            if ($this->error) {
                echo '<div class="error-panel"><p>'.$this->error.'</p></div>';
            } else if ($this->saved) {
                echo '<div class="updated-panel"><p>'.$titleUpdated.'</p></div>';
            }

        echo '</div>';
    }
}
