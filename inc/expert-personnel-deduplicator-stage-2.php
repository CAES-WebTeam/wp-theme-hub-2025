<?php
/**
 * Plugin Name: Duplicate User Manager
 * Plugin URI: https://caes.uga.edu
 * Description: Review and merge duplicate personnel/expert users, transferring post credits appropriately.
 * Version: 1.0.0
 * Author: CAES Web Team
 * Text Domain: duplicate-user-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Duplicate_User_Manager {

    const OPTION_DUPLICATES = 'dum_duplicate_data';
    const OPTION_DISMISSED = 'dum_dismissed_pairs';
    const OPTION_MERGED = 'dum_merged_pairs';
    const ITEMS_PER_PAGE = 10;

    private $credit_fields = [
        'post' => [
            'authors' => 'user',
            'artists' => 'user', 
            'expert_sources' => 'user'
        ],
        'publications' => [
            'authors' => 'user',
            'translator' => 'user',
            'artists' => 'user'
        ],
        'shorthand_story' => [
            'authors' => 'user',
            'artists' => 'user'
        ]
    ];

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_dum_dismiss_pair', [$this, 'ajax_dismiss_pair']);
        add_action('wp_ajax_dum_restore_pair', [$this, 'ajax_restore_pair']);
        add_action('wp_ajax_dum_get_user_posts', [$this, 'ajax_get_user_posts']);
        add_action('wp_ajax_dum_merge_users', [$this, 'ajax_merge_users']);
        add_action('wp_ajax_dum_upload_csv', [$this, 'ajax_upload_csv']);
        add_action('wp_ajax_dum_clear_data', [$this, 'ajax_clear_data']);
        add_action('wp_ajax_dum_get_user_comparison', [$this, 'ajax_get_user_comparison']);
    }

    public function add_admin_menu() {
        add_users_page(
            'Duplicate User Manager',
            'Duplicate Manager',
            'manage_options',
            'duplicate-user-manager',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'users_page_duplicate-user-manager') {
            return;
        }

        // Inline styles
        wp_register_style('dum-admin-styles', false);
        wp_enqueue_style('dum-admin-styles');
        wp_add_inline_style('dum-admin-styles', $this->get_inline_styles());

        // Inline scripts
        wp_register_script('dum-admin-script', false, ['jquery'], '1.0.0', true);
        wp_enqueue_script('dum-admin-script');
        
        wp_localize_script('dum-admin-script', 'dumAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dum_nonce'),
            'strings' => [
                'confirmDismiss' => 'Are you sure you want to dismiss this match?',
                'confirmMerge' => 'Are you sure you want to merge these users? This will transfer all post credits from the Expert user to the Personnel user.',
                'confirmClear' => 'Are you sure you want to clear all data? This cannot be undone.',
                'loading' => 'Loading...',
                'error' => 'An error occurred. Please try again.',
                'mergeSuccess' => 'Users merged successfully!',
                'dismissSuccess' => 'Match dismissed.',
                'restoreSuccess' => 'Match restored.',
            ]
        ]);
        
        wp_add_inline_script('dum-admin-script', $this->get_inline_scripts());
    }

    private function get_inline_styles() {
        return '
        .dum-wrap { max-width: 1400px; }
        .dum-content { margin-top: 20px; }
        .nav-tab .dum-badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; margin-left: 5px; background: #2271b1; color: #fff; }
        .nav-tab .dum-badge-secondary { background: #787c82; }
        .nav-tab .dum-badge-success { background: #00a32a; }
        .dum-notice { padding: 15px; margin: 20px 0; border-left: 4px solid #72aee6; background: #f0f6fc; }
        .dum-notice-info { border-color: #72aee6; background: #f0f6fc; }
        .dum-notice-warning { border-color: #dba617; background: #fcf9e8; }
        .dum-notice-success { border-color: #00a32a; background: #edfaef; }
        .dum-notice p { margin: 0; }
        .dum-filters { display: flex; align-items: center; gap: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-bottom: none; }
        .dum-filter-info { margin-left: auto; color: #646970; font-size: 13px; }
        .dum-duplicate-list { display: flex; flex-direction: column; gap: 0; }
        .dum-pair { background: #fff; border: 1px solid #c3c4c7; margin-bottom: -1px; }
        .dum-pair:last-child { margin-bottom: 0; }
        .dum-pair-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid #f0f0f1; background: #f9f9f9; }
        .dum-pair-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .dum-pair-actions .button { display: inline-flex; align-items: center; gap: 5px; }
        .dum-pair-actions .dashicons { font-size: 16px; width: 16px; height: 16px; line-height: 1; }
        .dum-confidence { font-weight: 600; padding: 5px 12px; border-radius: 4px; font-size: 12px; text-transform: uppercase; }
        .dum-confidence-high { background: #d1fae5; color: #065f46; }
        .dum-confidence-medium { background: #fef3c7; color: #92400e; }
        .dum-confidence-low { background: #fee2e2; color: #991b1b; }
        .dum-confidence-very-low { background: #f3f4f6; color: #4b5563; }
        .dum-pair-summary { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding: 15px 20px; }
        .dum-user-summary h4 { margin: 0 0 8px 0; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .dum-user-summary p { margin: 0; font-size: 13px; color: #50575e; }
        .dum-label { font-size: 10px; font-weight: 600; text-transform: uppercase; padding: 3px 8px; border-radius: 3px; }
        .dum-label-personnel { background: #dbeafe; color: #1e40af; }
        .dum-label-expert { background: #fce7f3; color: #9d174d; }
        .dum-edit-link { margin-left: 5px; color: #2271b1; text-decoration: none; }
        .dum-edit-link .dashicons { font-size: 14px; width: 14px; height: 14px; }
        .dum-warning { color: #b91c1c; font-size: 12px; font-style: italic; }
        .dum-pair-details { border-top: 1px solid #e0e0e0; padding: 20px; background: #f9f9f9; }
        .dum-match-reasons h4, .dum-posts-comparison h4 { margin: 0 0 10px 0; font-size: 14px; color: #1d2327; }
        .dum-match-reasons p { margin: 0; font-size: 13px; line-height: 1.6; color: #50575e; }
        .dum-posts-comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .dum-posts-column { background: #fff; border: 1px solid #e0e0e0; padding: 15px; border-radius: 4px; }
        .dum-posts-list { min-height: 60px; }
        .dum-post-list { margin: 0; padding: 0; list-style: none; max-height: 300px; overflow-y: auto; }
        .dum-post-list li { padding: 8px 0; border-bottom: 1px solid #f0f0f1; font-size: 13px; }
        .dum-post-list li:last-child { border-bottom: none; }
        .dum-post-list a { color: #2271b1; text-decoration: none; }
        .dum-post-list a:hover { text-decoration: underline; }
        .dum-post-meta { display: block; margin-top: 3px; }
        .dum-post-type { background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #50575e; margin-right: 5px; }
        .dum-credit-type { background: #e0f2fe; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #0369a1; }
        .dum-no-posts { color: #6b7280; font-style: italic; margin: 10px 0; }
        .dum-post-count { margin: 10px 0 0 0; font-size: 12px; color: #6b7280; font-weight: 500; }
        .dum-pagination { display: flex; align-items: center; justify-content: center; gap: 15px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-top: none; }
        .dum-page-info { color: #646970; font-size: 13px; }
        .dum-upload-section { background: #fff; padding: 30px; border: 1px solid #c3c4c7; }
        .dum-upload-section h2 { margin-top: 0; }
        .dum-column-list { background: #f6f7f7; padding: 15px 15px 15px 35px; margin: 15px 0; border-radius: 4px; }
        .dum-column-list li { margin: 5px 0; font-size: 13px; }
        .dum-column-list code { background: #e0e0e0; padding: 2px 6px; border-radius: 3px; }
        .dum-upload-form { margin: 20px 0; }
        #dum-upload-form { display: flex; gap: 15px; align-items: center; margin-top: 20px; }
        #dum-csv-file { padding: 8px; border: 2px dashed #c3c4c7; border-radius: 4px; background: #f6f7f7; }
        #dum-upload-progress { margin-top: 20px; display: flex; align-items: center; gap: 10px; color: #646970; }
        #dum-upload-result { margin-top: 20px; }
        .dum-data-management { margin-top: 40px; padding-top: 30px; border-top: 1px solid #e0e0e0; }
        .dum-data-management h3 { color: #b91c1c; }
        .dum-loading { display: flex; align-items: center; gap: 10px; padding: 20px; color: #646970; }
        .dum-loading .spinner { float: none; margin: 0; }
        .dum-toggle-details .dashicons { transition: transform 0.2s ease; }
        .dum-toggle-details.active .dashicons { transform: rotate(180deg); }
        .dum-pair.dum-removing { opacity: 0.5; pointer-events: none; }
        .dum-pair.dum-removed { animation: slideOut 0.3s ease forwards; }
        @keyframes slideOut { to { opacity: 0; max-height: 0; padding: 0; margin: 0; border: none; overflow: hidden; } }
        .dum-success-message { display: inline-flex; align-items: center; gap: 5px; background: #d1fae5; color: #065f46; padding: 8px 15px; border-radius: 4px; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Modal Styles */
        .dum-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 100000; display: flex; align-items: center; justify-content: center; }
        .dum-modal { background: #fff; border-radius: 8px; max-width: 1200px; width: 95%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
        .dum-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px; border-bottom: 1px solid #e0e0e0; background: #f9f9f9; }
        .dum-modal-header h2 { margin: 0; font-size: 18px; }
        .dum-modal-close { background: none; border: none; cursor: pointer; padding: 5px; font-size: 20px; color: #646970; }
        .dum-modal-close:hover { color: #1d2327; }
        .dum-modal-body { padding: 20px; overflow-y: auto; flex: 1; }
        .dum-modal-footer { padding: 15px 20px; border-top: 1px solid #e0e0e0; background: #f9f9f9; display: flex; justify-content: flex-end; gap: 10px; }
        
        /* Comparison Table */
        .dum-comparison-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .dum-comparison-table th, .dum-comparison-table td { padding: 10px 15px; text-align: left; border: 1px solid #e0e0e0; }
        .dum-comparison-table th { background: #f0f0f1; font-weight: 600; }
        .dum-comparison-table thead th:first-child { width: 180px; }
        .dum-comparison-table .dum-col-personnel { background: #f0f9ff; }
        .dum-comparison-table .dum-col-expert { background: #fff5f5; }
        .dum-comparison-table .dum-col-match { width: 100px; text-align: center; }
        .dum-row-exact { background: #ecfdf5 !important; }
        .dum-row-similar { background: #fefce8 !important; }
        .dum-row-different { background: #fff !important; }
        .dum-match-exact { color: #059669; font-weight: 500; }
        .dum-match-similar { color: #d97706; font-weight: 500; }
        .dum-match-none { color: #6b7280; }
        .dum-field-category { background: #1d2327 !important; color: #fff; font-weight: 600; }
        .dum-field-category td { background: #1d2327 !important; color: #fff; }
        .dum-empty-value { color: #9ca3af; font-style: italic; }
        
        @media screen and (max-width: 1200px) {
            .dum-pair-summary { grid-template-columns: 1fr; }
            .dum-posts-comparison { grid-template-columns: 1fr; }
        }
        @media screen and (max-width: 782px) {
            .dum-pair-header { flex-direction: column; gap: 15px; align-items: flex-start; }
            .dum-filters { flex-direction: column; align-items: flex-start; }
            .dum-filter-info { margin-left: 0; }
        }
        ';
    }

    private function get_inline_scripts() {
        return '
        (function($) {
            "use strict";
            
            $(document).ready(function() { DUM.init(); });
            
            var DUM = {
                init: function() {
                    this.bindToggleDetails();
                    this.bindDismissPair();
                    this.bindRestorePair();
                    this.bindLoadPosts();
                    this.bindMergePair();
                    this.bindUploadForm();
                    this.bindClearData();
                    this.bindConfidenceFilter();
                    this.bindCompareUsers();
                    this.bindModalClose();
                },
                
                bindToggleDetails: function() {
                    $(document).on("click", ".dum-toggle-details", function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var $pair = $btn.closest(".dum-pair");
                        var $details = $pair.find(".dum-pair-details");
                        $btn.toggleClass("active");
                        $details.slideToggle(200);
                    });
                },
                
                bindCompareUsers: function() {
                    $(document).on("click", ".dum-compare-users", function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var personnelId = $btn.data("personnel-id");
                        var expertId = $btn.data("expert-id");
                        
                        DUM.showModal("Loading comparison...", "<div class=\"dum-loading\"><span class=\"spinner is-active\"></span> " + dumAjax.strings.loading + "</div>");
                        
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: {
                                action: "dum_get_user_comparison",
                                nonce: dumAjax.nonce,
                                personnel_id: personnelId,
                                expert_id: expertId
                            },
                            success: function(response) {
                                if (response.success) {
                                    DUM.showModal("Detailed User Comparison", response.data.html);
                                } else {
                                    DUM.closeModal();
                                    DUM.showNotice(response.data.message || dumAjax.strings.error, "error");
                                }
                            },
                            error: function() {
                                DUM.closeModal();
                                DUM.showNotice(dumAjax.strings.error, "error");
                            }
                        });
                    });
                },
                
                showModal: function(title, content) {
                    this.closeModal();
                    var modal = $("<div class=\"dum-modal-overlay\"><div class=\"dum-modal\"><div class=\"dum-modal-header\"><h2>" + title + "</h2><button type=\"button\" class=\"dum-modal-close\">&times;</button></div><div class=\"dum-modal-body\">" + content + "</div><div class=\"dum-modal-footer\"><button type=\"button\" class=\"button dum-modal-close-btn\">Close</button></div></div></div>");
                    $("body").append(modal);
                    $("body").css("overflow", "hidden");
                },
                
                closeModal: function() {
                    $(".dum-modal-overlay").remove();
                    $("body").css("overflow", "");
                },
                
                bindModalClose: function() {
                    $(document).on("click", ".dum-modal-close, .dum-modal-close-btn", function(e) {
                        e.preventDefault();
                        DUM.closeModal();
                    });
                    $(document).on("click", ".dum-modal-overlay", function(e) {
                        if ($(e.target).hasClass("dum-modal-overlay")) {
                            DUM.closeModal();
                        }
                    });
                    $(document).on("keydown", function(e) {
                        if (e.key === "Escape") {
                            DUM.closeModal();
                        }
                    });
                },
                
                bindDismissPair: function() {
                    $(document).on("click", ".dum-dismiss-pair", function(e) {
                        e.preventDefault();
                        if (!confirm(dumAjax.strings.confirmDismiss)) return;
                        var $btn = $(this);
                        var $pair = $btn.closest(".dum-pair");
                        var index = $btn.data("index");
                        $pair.addClass("dum-removing");
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: { action: "dum_dismiss_pair", nonce: dumAjax.nonce, index: index },
                            success: function(response) {
                                if (response.success) {
                                    $pair.addClass("dum-removed");
                                    setTimeout(function() {
                                        $pair.remove();
                                        DUM.updateBadgeCount("review", response.data.remaining);
                                        DUM.showNotice(dumAjax.strings.dismissSuccess, "success");
                                    }, 300);
                                } else {
                                    $pair.removeClass("dum-removing");
                                    DUM.showNotice(response.data.message || dumAjax.strings.error, "error");
                                }
                            },
                            error: function() {
                                $pair.removeClass("dum-removing");
                                DUM.showNotice(dumAjax.strings.error, "error");
                            }
                        });
                    });
                },
                
                bindRestorePair: function() {
                    $(document).on("click", ".dum-restore-pair", function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var $row = $btn.closest("tr");
                        var index = $btn.data("index");
                        $btn.prop("disabled", true).text("Restoring...");
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: { action: "dum_restore_pair", nonce: dumAjax.nonce, index: index },
                            success: function(response) {
                                if (response.success) {
                                    $row.fadeOut(300, function() { $(this).remove(); });
                                    DUM.showNotice(dumAjax.strings.restoreSuccess, "success");
                                } else {
                                    $btn.prop("disabled", false).html("<span class=\"dashicons dashicons-undo\"></span> Restore");
                                    DUM.showNotice(response.data.message || dumAjax.strings.error, "error");
                                }
                            },
                            error: function() {
                                $btn.prop("disabled", false).html("<span class=\"dashicons dashicons-undo\"></span> Restore");
                                DUM.showNotice(dumAjax.strings.error, "error");
                            }
                        });
                    });
                },
                
                bindLoadPosts: function() {
                    $(document).on("click", ".dum-load-posts", function(e) {
                        e.preventDefault();
                        var $btn = $(this);
                        var userId = $btn.data("user-id");
                        var targetId = $btn.data("target");
                        var $container = $("#" + targetId);
                        $container.html("<div class=\"dum-loading\"><span class=\"spinner is-active\"></span> " + dumAjax.strings.loading + "</div>");
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: { action: "dum_get_user_posts", nonce: dumAjax.nonce, user_id: userId },
                            success: function(response) {
                                if (response.success) {
                                    $container.html(response.data.html);
                                } else {
                                    $container.html("<p class=\"dum-error\">" + (response.data.message || dumAjax.strings.error) + "</p>");
                                }
                            },
                            error: function() {
                                $container.html("<p class=\"dum-error\">" + dumAjax.strings.error + "</p>");
                            }
                        });
                    });
                },
                
                bindMergePair: function() {
                    $(document).on("click", ".dum-merge-pair", function(e) {
                        e.preventDefault();
                        if (!confirm(dumAjax.strings.confirmMerge)) return;
                        var $btn = $(this);
                        var $pair = $btn.closest(".dum-pair");
                        var personnelId = $btn.data("personnel-id");
                        var expertId = $btn.data("expert-id");
                        var index = $btn.data("index");
                        $btn.prop("disabled", true).html("<span class=\"spinner is-active\" style=\"float:none;margin:0 5px 0 0;\"></span> Merging...");
                        $pair.addClass("dum-removing");
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: { action: "dum_merge_users", nonce: dumAjax.nonce, personnel_id: personnelId, expert_id: expertId, index: index },
                            success: function(response) {
                                if (response.success) {
                                    $pair.addClass("dum-removed");
                                    setTimeout(function() {
                                        $pair.remove();
                                        DUM.updateBadgeCount("review", response.data.remaining);
                                        DUM.showNotice(response.data.message || dumAjax.strings.mergeSuccess, "success");
                                    }, 300);
                                } else {
                                    $pair.removeClass("dum-removing");
                                    $btn.prop("disabled", false).html("<span class=\"dashicons dashicons-migrate\"></span> Merge Users");
                                    DUM.showNotice(response.data.message || dumAjax.strings.error, "error");
                                }
                            },
                            error: function() {
                                $pair.removeClass("dum-removing");
                                $btn.prop("disabled", false).html("<span class=\"dashicons dashicons-migrate\"></span> Merge Users");
                                DUM.showNotice(dumAjax.strings.error, "error");
                            }
                        });
                    });
                },
                
                bindUploadForm: function() {
                    $("#dum-upload-form").on("submit", function(e) {
                        e.preventDefault();
                        var $form = $(this);
                        var $progress = $("#dum-upload-progress");
                        var $result = $("#dum-upload-result");
                        var formData = new FormData(this);
                        formData.append("action", "dum_upload_csv");
                        formData.append("nonce", dumAjax.nonce);
                        $form.find("button").prop("disabled", true);
                        $progress.show();
                        $result.empty();
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                $form.find("button").prop("disabled", false);
                                $progress.hide();
                                if (response.success) {
                                    $result.html("<div class=\"dum-notice dum-notice-success\"><p>" + response.data.message + "</p></div>");
                                    setTimeout(function() { window.location.href = "?page=duplicate-user-manager&tab=review"; }, 1500);
                                } else {
                                    $result.html("<div class=\"dum-notice dum-notice-warning\"><p>" + (response.data.message || dumAjax.strings.error) + "</p></div>");
                                }
                            },
                            error: function() {
                                $form.find("button").prop("disabled", false);
                                $progress.hide();
                                $result.html("<div class=\"dum-notice dum-notice-warning\"><p>" + dumAjax.strings.error + "</p></div>");
                            }
                        });
                    });
                },
                
                bindClearData: function() {
                    $("#dum-clear-data").on("click", function(e) {
                        e.preventDefault();
                        if (!confirm(dumAjax.strings.confirmClear)) return;
                        var $btn = $(this);
                        $btn.prop("disabled", true).text("Clearing...");
                        $.ajax({
                            url: dumAjax.ajaxurl,
                            type: "POST",
                            data: { action: "dum_clear_data", nonce: dumAjax.nonce },
                            success: function(response) {
                                if (response.success) {
                                    window.location.reload();
                                } else {
                                    $btn.prop("disabled", false).html("<span class=\"dashicons dashicons-trash\"></span> Clear All Data");
                                    DUM.showNotice(response.data.message || dumAjax.strings.error, "error");
                                }
                            },
                            error: function() {
                                $btn.prop("disabled", false).html("<span class=\"dashicons dashicons-trash\"></span> Clear All Data");
                                DUM.showNotice(dumAjax.strings.error, "error");
                            }
                        });
                    });
                },
                
                bindConfidenceFilter: function() {
                    $("#dum-confidence-filter").on("change", function() {
                        var minConfidence = parseInt($(this).val()) || 0;
                        $(".dum-pair").each(function() {
                            var $pair = $(this);
                            var confidence = parseInt($pair.data("confidence")) || 0;
                            if (minConfidence === 0 || confidence >= minConfidence) {
                                $pair.show();
                            } else {
                                $pair.hide();
                            }
                        });
                        var visible = $(".dum-pair:visible").length;
                        var total = $(".dum-pair").length;
                        $(".dum-filter-info").text("Showing " + visible + " of " + total + " pairs" + (minConfidence > 0 ? " (filtered)" : ""));
                    });
                },
                
                updateBadgeCount: function(tab, count) {
                    var $badge = $(".nav-tab[href*=\"tab=" + tab + "\"] .dum-badge");
                    if (count > 0) {
                        if ($badge.length) $badge.text(count);
                    } else {
                        $badge.remove();
                    }
                },
                
                showNotice: function(message, type) {
                    var $notice = $("<div class=\"dum-notice dum-notice-" + type + " dum-temp-notice\"><p>" + message + "</p></div>");
                    $(".dum-content").prepend($notice);
                    setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 4000);
                }
            };
            
            window.DUM = DUM;
        })(jQuery);
        ';
    }

    public function render_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'review';
        $duplicates = get_option(self::OPTION_DUPLICATES, []);
        $dismissed = get_option(self::OPTION_DISMISSED, []);
        $merged = get_option(self::OPTION_MERGED, []);
        
        ?>
        <div class="wrap dum-wrap">
            <h1>Duplicate User Manager</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=duplicate-user-manager&tab=review" class="nav-tab <?php echo $current_tab === 'review' ? 'nav-tab-active' : ''; ?>">
                    Review Duplicates
                    <?php if (!empty($duplicates)): ?><span class="dum-badge"><?php echo count($duplicates); ?></span><?php endif; ?>
                </a>
                <a href="?page=duplicate-user-manager&tab=dismissed" class="nav-tab <?php echo $current_tab === 'dismissed' ? 'nav-tab-active' : ''; ?>">
                    Dismissed
                    <?php if (!empty($dismissed)): ?><span class="dum-badge dum-badge-secondary"><?php echo count($dismissed); ?></span><?php endif; ?>
                </a>
                <a href="?page=duplicate-user-manager&tab=merged" class="nav-tab <?php echo $current_tab === 'merged' ? 'nav-tab-active' : ''; ?>">
                    Merged
                    <?php if (!empty($merged)): ?><span class="dum-badge dum-badge-success"><?php echo count($merged); ?></span><?php endif; ?>
                </a>
                <a href="?page=duplicate-user-manager&tab=upload" class="nav-tab <?php echo $current_tab === 'upload' ? 'nav-tab-active' : ''; ?>">
                    Upload CSV
                </a>
            </nav>

            <div class="dum-content">
                <?php
                switch ($current_tab) {
                    case 'dismissed':
                        $this->render_dismissed_tab($dismissed);
                        break;
                    case 'merged':
                        $this->render_merged_tab($merged);
                        break;
                    case 'upload':
                        $this->render_upload_tab();
                        break;
                    default:
                        $this->render_review_tab($duplicates);
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    private function render_review_tab($duplicates) {
        if (empty($duplicates)) {
            echo '<div class="dum-notice dum-notice-info"><p>No duplicate pairs to review. <a href="?page=duplicate-user-manager&tab=upload">Upload a CSV file</a> to get started.</p></div>';
            return;
        }

        $total_items = count($duplicates);
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_pages = ceil($total_items / self::ITEMS_PER_PAGE);
        $offset = ($current_page - 1) * self::ITEMS_PER_PAGE;
        $paged_duplicates = array_slice($duplicates, $offset, self::ITEMS_PER_PAGE, true);
        ?>
        <div class="dum-filters">
            <label>Filter by confidence:
                <select id="dum-confidence-filter">
                    <option value="">All</option>
                    <option value="100">100%</option>
                    <option value="90">90%+</option>
                    <option value="70">70%+</option>
                    <option value="50">50%+</option>
                </select>
            </label>
            <span class="dum-filter-info">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + self::ITEMS_PER_PAGE, $total_items); ?> of <?php echo $total_items; ?> pairs</span>
        </div>

        <div class="dum-duplicate-list">
            <?php foreach ($paged_duplicates as $index => $pair): ?>
                <?php $this->render_duplicate_pair($pair, $index); ?>
            <?php endforeach; ?>
        </div>

        <?php $this->render_pagination($current_page, $total_pages, 'review');
    }

    private function render_duplicate_pair($pair, $index) {
        $confidence = isset($pair['confidence']) ? $pair['confidence'] : 'N/A';
        $confidence_class = $this->get_confidence_class($confidence);
        $personnel_user = get_user_by('ID', $pair['personnel_wp_id']);
        $expert_user = get_user_by('ID', $pair['expert_wp_id']);
        ?>
        <div class="dum-pair" data-index="<?php echo esc_attr($index); ?>" data-confidence="<?php echo esc_attr(intval($confidence)); ?>">
            <div class="dum-pair-header">
                <div class="dum-confidence <?php echo esc_attr($confidence_class); ?>"><?php echo esc_html($confidence); ?> confidence</div>
                <div class="dum-pair-actions">
                    <button type="button" class="button dum-compare-users" 
                            data-personnel-id="<?php echo esc_attr($pair['personnel_wp_id']); ?>"
                            data-expert-id="<?php echo esc_attr($pair['expert_wp_id']); ?>">
                        <span class="dashicons dashicons-editor-table"></span> Compare Fields
                    </button>
                    <button type="button" class="button dum-toggle-details">
                        <span class="dashicons dashicons-arrow-down-alt2"></span> Posts
                    </button>
                    <button type="button" class="button dum-dismiss-pair" data-index="<?php echo esc_attr($index); ?>">
                        <span class="dashicons dashicons-dismiss"></span> Not a Match
                    </button>
                    <button type="button" class="button button-primary dum-merge-pair" 
                            data-personnel-id="<?php echo esc_attr($pair['personnel_wp_id']); ?>"
                            data-expert-id="<?php echo esc_attr($pair['expert_wp_id']); ?>"
                            data-index="<?php echo esc_attr($index); ?>">
                        <span class="dashicons dashicons-migrate"></span> Merge Users
                    </button>
                </div>
            </div>
            
            <div class="dum-pair-summary">
                <div class="dum-user-summary">
                    <h4>
                        <span class="dum-label dum-label-personnel">Personnel</span>
                        <?php echo esc_html($pair['personnel_name']); ?>
                        <?php if ($personnel_user): ?>
                            <a href="<?php echo esc_url(get_edit_user_link($personnel_user->ID)); ?>" target="_blank" class="dum-edit-link"><span class="dashicons dashicons-external"></span></a>
                        <?php else: ?>
                            <span class="dum-warning">User not found!</span>
                        <?php endif; ?>
                    </h4>
                    <p><?php echo esc_html($pair['personnel_email'] ?? 'No email'); ?> &bull; ID: <?php echo esc_html($pair['personnel_wp_id']); ?></p>
                </div>
                <div class="dum-user-summary">
                    <h4>
                        <span class="dum-label dum-label-expert">Expert</span>
                        <?php echo esc_html($pair['expert_name']); ?>
                        <?php if ($expert_user): ?>
                            <a href="<?php echo esc_url(get_edit_user_link($expert_user->ID)); ?>" target="_blank" class="dum-edit-link"><span class="dashicons dashicons-external"></span></a>
                        <?php else: ?>
                            <span class="dum-warning">User not found!</span>
                        <?php endif; ?>
                    </h4>
                    <p><?php echo esc_html($pair['expert_email'] ?? 'No email'); ?> &bull; ID: <?php echo esc_html($pair['expert_wp_id']); ?></p>
                </div>
            </div>

            <div class="dum-pair-details" style="display: none;">
                <div class="dum-match-reasons">
                    <h4>Match Reasons</h4>
                    <p><?php echo esc_html($pair['match_reasons'] ?? 'N/A'); ?></p>
                </div>
                
                <div class="dum-posts-comparison">
                    <div class="dum-posts-column">
                        <h4>Personnel User Posts</h4>
                        <div class="dum-posts-list" id="dum-posts-personnel-<?php echo esc_attr($pair['personnel_wp_id']); ?>">
                            <button type="button" class="button dum-load-posts" data-user-id="<?php echo esc_attr($pair['personnel_wp_id']); ?>" data-target="dum-posts-personnel-<?php echo esc_attr($pair['personnel_wp_id']); ?>">Load Posts</button>
                        </div>
                    </div>
                    <div class="dum-posts-column">
                        <h4>Expert User Posts</h4>
                        <div class="dum-posts-list" id="dum-posts-expert-<?php echo esc_attr($pair['expert_wp_id']); ?>">
                            <button type="button" class="button dum-load-posts" data-user-id="<?php echo esc_attr($pair['expert_wp_id']); ?>" data-target="dum-posts-expert-<?php echo esc_attr($pair['expert_wp_id']); ?>">Load Posts</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_get_user_comparison() {
        check_ajax_referer('dum_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $personnel_id = isset($_POST['personnel_id']) ? intval($_POST['personnel_id']) : 0;
        $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
        
        if (!$personnel_id || !$expert_id) {
            wp_send_json_error(['message' => 'Invalid user IDs.']);
        }

        $personnel_user = get_user_by('ID', $personnel_id);
        $expert_user = get_user_by('ID', $expert_id);
        
        if (!$personnel_user || !$expert_user) {
            wp_send_json_error(['message' => 'One or both users not found.']);
        }

        $html = $this->generate_user_comparison_html($personnel_user, $expert_user);
        
        wp_send_json_success(['html' => $html]);
    }

    private function generate_user_comparison_html($personnel_user, $expert_user) {
        $personnel_meta = get_user_meta($personnel_user->ID);
        $expert_meta = get_user_meta($expert_user->ID);
        
        // Define field categories and fields to compare
        $field_groups = [
            'Basic Info' => [
                'ID' => [$personnel_user->ID, $expert_user->ID],
                'user_login' => [$personnel_user->user_login, $expert_user->user_login],
                'user_email' => [$personnel_user->user_email, $expert_user->user_email],
                'display_name' => [$personnel_user->display_name, $expert_user->display_name],
                'user_nicename' => [$personnel_user->user_nicename, $expert_user->user_nicename],
                'user_registered' => [$personnel_user->user_registered, $expert_user->user_registered],
                'user_url' => [$personnel_user->user_url, $expert_user->user_url],
            ],
            'Name Fields' => [
                'first_name' => [$this->get_meta_value($personnel_meta, 'first_name'), $this->get_meta_value($expert_meta, 'first_name')],
                'last_name' => [$this->get_meta_value($personnel_meta, 'last_name'), $this->get_meta_value($expert_meta, 'last_name')],
                'nickname' => [$this->get_meta_value($personnel_meta, 'nickname'), $this->get_meta_value($expert_meta, 'nickname')],
            ],
            'Contact Info' => [
                'phone' => [$this->get_meta_value($personnel_meta, 'phone'), $this->get_meta_value($expert_meta, 'phone')],
                'phone_number' => [$this->get_meta_value($personnel_meta, 'phone_number'), $this->get_meta_value($expert_meta, 'phone_number')],
                'mobile' => [$this->get_meta_value($personnel_meta, 'mobile'), $this->get_meta_value($expert_meta, 'mobile')],
            ],
            'Role & Capabilities' => [
                'roles' => [implode(', ', $personnel_user->roles), implode(', ', $expert_user->roles)],
            ],
            'Description' => [
                'description' => [$this->get_meta_value($personnel_meta, 'description'), $this->get_meta_value($expert_meta, 'description')],
            ],
        ];

        // Add ACF fields if they exist
        $acf_fields = $this->get_common_acf_fields($personnel_user->ID, $expert_user->ID);
        if (!empty($acf_fields)) {
            $field_groups['ACF/Custom Fields'] = $acf_fields;
        }

        ob_start();
        ?>
        <table class="dum-comparison-table">
            <thead>
                <tr>
                    <th>Field</th>
                    <th class="dum-col-personnel">Personnel User</th>
                    <th class="dum-col-expert">Expert User</th>
                    <th class="dum-col-match">Match</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($field_groups as $category => $fields): ?>
                    <tr class="dum-field-category">
                        <td colspan="4"><?php echo esc_html($category); ?></td>
                    </tr>
                    <?php foreach ($fields as $field_name => $values): 
                        $val1 = $values[0];
                        $val2 = $values[1];
                        $match = $this->compare_values($val1, $val2);
                    ?>
                    <tr class="<?php echo esc_attr($match['class']); ?>">
                        <td><strong><?php echo esc_html($field_name); ?></strong></td>
                        <td class="dum-col-personnel"><?php echo $this->format_value($val1); ?></td>
                        <td class="dum-col-expert"><?php echo $this->format_value($val2); ?></td>
                        <td class="dum-col-match">
                            <?php if ($match['exact']): ?>
                                <span class="dum-match-exact">✓ Exact</span>
                            <?php elseif ($match['similar']): ?>
                                <span class="dum-match-similar">~ Similar</span>
                            <?php else: ?>
                                <span class="dum-match-none">✗ Different</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    private function get_meta_value($meta, $key) {
        return isset($meta[$key][0]) ? $meta[$key][0] : '';
    }

    private function format_value($value) {
        if ($value === '' || $value === null) {
            return '<span class="dum-empty-value">(empty)</span>';
        }
        if (strlen($value) > 200) {
            return esc_html(substr($value, 0, 200)) . '...';
        }
        return esc_html($value);
    }

    private function get_common_acf_fields($user_id_1, $user_id_2) {
        if (!function_exists('get_field_objects')) {
            return [];
        }

        $fields = [];
        $field_objects_1 = get_field_objects('user_' . $user_id_1);
        $field_objects_2 = get_field_objects('user_' . $user_id_2);
        
        if (!$field_objects_1 && !$field_objects_2) {
            return [];
        }

        $all_field_names = array_unique(array_merge(
            $field_objects_1 ? array_keys($field_objects_1) : [],
            $field_objects_2 ? array_keys($field_objects_2) : []
        ));

        foreach ($all_field_names as $field_name) {
            $val1 = get_field($field_name, 'user_' . $user_id_1);
            $val2 = get_field($field_name, 'user_' . $user_id_2);
            
            // Convert arrays/objects to string for display
            if (is_array($val1)) $val1 = json_encode($val1);
            if (is_array($val2)) $val2 = json_encode($val2);
            if (is_object($val1)) $val1 = json_encode($val1);
            if (is_object($val2)) $val2 = json_encode($val2);
            
            $fields[$field_name] = [$val1, $val2];
        }

        return $fields;
    }

    private function compare_values($val1, $val2) {
        $val1_str = is_string($val1) ? strtolower(trim($val1)) : strval($val1);
        $val2_str = is_string($val2) ? strtolower(trim($val2)) : strval($val2);
        
        // Both empty
        if (($val1 === '' || $val1 === null) && ($val2 === '' || $val2 === null)) {
            return ['exact' => true, 'similar' => false, 'class' => 'dum-row-exact'];
        }
        
        // One empty, one not
        if (($val1 === '' || $val1 === null) || ($val2 === '' || $val2 === null)) {
            return ['exact' => false, 'similar' => false, 'class' => 'dum-row-different'];
        }
        
        // Normalize phone numbers
        $val1_normalized = preg_replace('/[^0-9]/', '', $val1_str);
        $val2_normalized = preg_replace('/[^0-9]/', '', $val2_str);
        
        if ($val1_str === $val2_str || ($val1_normalized === $val2_normalized && !empty($val1_normalized) && strlen($val1_normalized) >= 7)) {
            return ['exact' => true, 'similar' => false, 'class' => 'dum-row-exact'];
        }
        
        similar_text($val1_str, $val2_str, $percent);
        if ($percent > 70) {
            return ['exact' => false, 'similar' => true, 'class' => 'dum-row-similar'];
        }
        
        return ['exact' => false, 'similar' => false, 'class' => 'dum-row-different'];
    }

    private function get_confidence_class($confidence) {
        $value = intval(str_replace('%', '', $confidence));
        if ($value >= 90) return 'dum-confidence-high';
        if ($value >= 70) return 'dum-confidence-medium';
        if ($value >= 50) return 'dum-confidence-low';
        return 'dum-confidence-very-low';
    }

    private function render_pagination($current_page, $total_pages, $tab) {
        if ($total_pages <= 1) return;
        ?>
        <div class="dum-pagination">
            <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg(['tab' => $tab, 'paged' => $current_page - 1])); ?>" class="button">&laquo; Previous</a>
            <?php endif; ?>
            <span class="dum-page-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
            <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg(['tab' => $tab, 'paged' => $current_page + 1])); ?>" class="button">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_dismissed_tab($dismissed) {
        if (empty($dismissed)) {
            echo '<div class="dum-notice dum-notice-info"><p>No dismissed pairs.</p></div>';
            return;
        }

        $total_items = count($dismissed);
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_pages = ceil($total_items / self::ITEMS_PER_PAGE);
        $offset = ($current_page - 1) * self::ITEMS_PER_PAGE;
        $paged_dismissed = array_slice($dismissed, $offset, self::ITEMS_PER_PAGE, true);
        ?>
        <div class="dum-dismissed-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Confidence</th>
                        <th>Personnel User</th>
                        <th>Expert User</th>
                        <th>Dismissed At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged_dismissed as $index => $pair): ?>
                    <tr data-index="<?php echo esc_attr($index); ?>">
                        <td><?php echo esc_html($pair['confidence']); ?></td>
                        <td><?php echo esc_html($pair['personnel_name']); ?><br><small>ID: <?php echo esc_html($pair['personnel_wp_id']); ?></small></td>
                        <td><?php echo esc_html($pair['expert_name']); ?><br><small>ID: <?php echo esc_html($pair['expert_wp_id']); ?></small></td>
                        <td><?php echo esc_html($pair['dismissed_at'] ?? 'N/A'); ?></td>
                        <td><button type="button" class="button dum-restore-pair" data-index="<?php echo esc_attr($index); ?>"><span class="dashicons dashicons-undo"></span> Restore</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $this->render_pagination($current_page, $total_pages, 'dismissed');
    }

    private function render_merged_tab($merged) {
        if (empty($merged)) {
            echo '<div class="dum-notice dum-notice-info"><p>No merged pairs yet.</p></div>';
            return;
        }

        $total_items = count($merged);
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_pages = ceil($total_items / self::ITEMS_PER_PAGE);
        $offset = ($current_page - 1) * self::ITEMS_PER_PAGE;
        $paged_merged = array_slice($merged, $offset, self::ITEMS_PER_PAGE, true);
        ?>
        <div class="dum-merged-list">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Personnel User</th>
                        <th>Expert User (merged)</th>
                        <th>Posts Transferred</th>
                        <th>Merged At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paged_merged as $pair): ?>
                    <tr>
                        <td><?php echo esc_html($pair['personnel_name']); ?><br><small>ID: <?php echo esc_html($pair['personnel_wp_id']); ?></small></td>
                        <td><?php echo esc_html($pair['expert_name']); ?><br><small>ID: <?php echo esc_html($pair['expert_wp_id']); ?></small></td>
                        <td><?php echo esc_html($pair['posts_transferred'] ?? 0); ?></td>
                        <td><?php echo esc_html($pair['merged_at'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php $this->render_pagination($current_page, $total_pages, 'merged');
    }

    private function render_upload_tab() {
        $duplicates = get_option(self::OPTION_DUPLICATES, []);
        ?>
        <div class="dum-upload-section">
            <h2>Upload Duplicate Users CSV</h2>
            
            <?php if (!empty($duplicates)): ?>
            <div class="dum-notice dum-notice-warning">
                <p><strong>Note:</strong> You already have <?php echo count($duplicates); ?> duplicate pairs loaded. Uploading a new CSV will replace the existing data.</p>
            </div>
            <?php endif; ?>

            <div class="dum-upload-form">
                <p>Upload a CSV file containing potential duplicate users. The CSV should have the following columns:</p>
                <ul class="dum-column-list">
                    <li><code>Confidence</code> - Match confidence percentage</li>
                    <li><code>Personnel WP ID</code> - WordPress user ID for personnel</li>
                    <li><code>Personnel Name</code> - Display name</li>
                    <li><code>Personnel Email</code> - Email address</li>
                    <li><code>Personnel Phone</code> - Phone number</li>
                    <li><code>personnel_id</code> - Custom personnel ID</li>
                    <li><code>Expert WP ID</code> - WordPress user ID for expert</li>
                    <li><code>Expert Name</code> - Display name</li>
                    <li><code>Expert Email</code> - Email address</li>
                    <li><code>Expert Phone</code> - Phone number</li>
                    <li><code>expert_source_id</code> - Expert source ID</li>
                    <li><code>expert_writer_id</code> - Expert writer ID</li>
                    <li><code>Match Reasons</code> - Description of why matched</li>
                </ul>

                <form id="dum-upload-form" enctype="multipart/form-data">
                    <input type="file" name="csv_file" id="dum-csv-file" accept=".csv" required>
                    <button type="submit" class="button button-primary"><span class="dashicons dashicons-upload"></span> Upload CSV</button>
                </form>
                
                <div id="dum-upload-progress" style="display: none;">
                    <span class="spinner is-active"></span>
                    <span>Processing CSV file...</span>
                </div>
                
                <div id="dum-upload-result"></div>
            </div>

            <?php if (!empty($duplicates)): ?>
            <div class="dum-data-management">
                <h3>Data Management</h3>
                <p>Clear all duplicate data and start fresh:</p>
                <button type="button" id="dum-clear-data" class="button button-secondary"><span class="dashicons dashicons-trash"></span> Clear All Data</button>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function ajax_upload_csv() {
        check_ajax_referer('dum_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        if (!isset($_FILES['csv_file'])) wp_send_json_error(['message' => 'No file uploaded.']);

        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) wp_send_json_error(['message' => 'File upload error.']);

        $csv_data = file_get_contents($file['tmp_name']);
        $csv_data = preg_replace('/^\xEF\xBB\xBF/', '', $csv_data);
        
        $lines = explode("\n", $csv_data);
        $header = str_getcsv(array_shift($lines));
        
        $header_map = [
            'Confidence' => 'confidence',
            'Personnel WP ID' => 'personnel_wp_id',
            'Personnel Name' => 'personnel_name',
            'Personnel Email' => 'personnel_email',
            'Personnel Phone' => 'personnel_phone',
            'personnel_id' => 'personnel_id',
            'Expert WP ID' => 'expert_wp_id',
            'Expert Name' => 'expert_name',
            'Expert Email' => 'expert_email',
            'Expert Phone' => 'expert_phone',
            'expert_source_id' => 'expert_source_id',
            'expert_writer_id' => 'expert_writer_id',
            'Match Reasons' => 'match_reasons',
        ];

        $duplicates = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            $row = str_getcsv($line);
            if (count($row) < count($header)) continue;
            
            $pair = [];
            foreach ($header as $i => $col) {
                $key = isset($header_map[$col]) ? $header_map[$col] : $col;
                $pair[$key] = isset($row[$i]) ? $row[$i] : '';
            }
            
            if (empty($pair['personnel_wp_id']) || empty($pair['expert_wp_id'])) continue;
            $duplicates[] = $pair;
        }

        if (empty($duplicates)) wp_send_json_error(['message' => 'No valid duplicate pairs found in CSV.']);

        update_option(self::OPTION_DUPLICATES, $duplicates);
        wp_send_json_success(['message' => sprintf('Successfully imported %d duplicate pairs.', count($duplicates)), 'count' => count($duplicates)]);
    }

    public function ajax_clear_data() {
        check_ajax_referer('dum_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);
        delete_option(self::OPTION_DUPLICATES);
        delete_option(self::OPTION_DISMISSED);
        delete_option(self::OPTION_MERGED);
        wp_send_json_success(['message' => 'All data cleared.']);
    }

    public function ajax_dismiss_pair() {
        check_ajax_referer('dum_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
        $duplicates = get_option(self::OPTION_DUPLICATES, []);
        $dismissed = get_option(self::OPTION_DISMISSED, []);
        
        if (!isset($duplicates[$index])) wp_send_json_error(['message' => 'Invalid pair index.']);

        $pair = $duplicates[$index];
        $pair['dismissed_at'] = current_time('mysql');
        $dismissed[] = $pair;
        unset($duplicates[$index]);
        $duplicates = array_values($duplicates);
        
        update_option(self::OPTION_DUPLICATES, $duplicates);
        update_option(self::OPTION_DISMISSED, $dismissed);
        wp_send_json_success(['message' => 'Pair dismissed.', 'remaining' => count($duplicates)]);
    }

    public function ajax_restore_pair() {
        check_ajax_referer('dum_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
        $duplicates = get_option(self::OPTION_DUPLICATES, []);
        $dismissed = get_option(self::OPTION_DISMISSED, []);
        
        if (!isset($dismissed[$index])) wp_send_json_error(['message' => 'Invalid pair index.']);

        $pair = $dismissed[$index];
        unset($pair['dismissed_at']);
        $duplicates[] = $pair;
        unset($dismissed[$index]);
        $dismissed = array_values($dismissed);
        
        update_option(self::OPTION_DUPLICATES, $duplicates);
        update_option(self::OPTION_DISMISSED, $dismissed);
        wp_send_json_success(['message' => 'Pair restored.']);
    }

    public function ajax_get_user_posts() {
        check_ajax_referer('dum_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) wp_send_json_error(['message' => 'Invalid user ID.']);

        $posts = $this->get_posts_for_user($user_id);
        
        ob_start();
        if (empty($posts)) {
            echo '<p class="dum-no-posts">No posts found for this user.</p>';
        } else {
            echo '<ul class="dum-post-list">';
            foreach ($posts as $post) {
                $post_type_obj = get_post_type_object($post['post_type']);
                $post_type_label = $post_type_obj ? $post_type_obj->labels->singular_name : $post['post_type'];
                ?>
                <li>
                    <a href="<?php echo esc_url(get_edit_post_link($post['id'])); ?>" target="_blank"><?php echo esc_html($post['title']); ?></a>
                    <span class="dum-post-meta">
                        <span class="dum-post-type"><?php echo esc_html($post_type_label); ?></span>
                        <span class="dum-credit-type"><?php echo esc_html($post['credit_type']); ?></span>
                    </span>
                </li>
                <?php
            }
            echo '</ul>';
            echo '<p class="dum-post-count">Total: ' . count($posts) . ' post(s)</p>';
        }
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html, 'count' => count($posts)]);
    }

    private function get_posts_for_user($user_id) {
        global $wpdb;
        $posts = [];
        
        foreach ($this->credit_fields as $post_type => $fields) {
            foreach ($fields as $repeater_field => $user_subfield) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT DISTINCT p.ID, p.post_title, p.post_type, pm.meta_key
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = %s
                     AND p.post_status IN ('publish', 'draft', 'pending')
                     AND pm.meta_key LIKE %s
                     AND pm.meta_value = %s",
                    $post_type,
                    $wpdb->esc_like($repeater_field) . '_%_' . $user_subfield,
                    $user_id
                ), ARRAY_A);
                
                foreach ($results as $result) {
                    $key = $result['ID'] . '_' . $repeater_field;
                    if (!isset($posts[$key])) {
                        $posts[$key] = [
                            'id' => $result['ID'],
                            'title' => $result['post_title'],
                            'post_type' => $result['post_type'],
                            'credit_type' => ucfirst(str_replace('_', ' ', $repeater_field)),
                            'field' => $repeater_field,
                            'meta_key' => $result['meta_key']
                        ];
                    }
                }
            }
        }
        return array_values($posts);
    }

    public function ajax_merge_users() {
        check_ajax_referer('dum_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

        $personnel_id = isset($_POST['personnel_id']) ? intval($_POST['personnel_id']) : 0;
        $expert_id = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
        
        if (!$personnel_id || !$expert_id) wp_send_json_error(['message' => 'Invalid user IDs.']);

        $expert_posts = $this->get_posts_for_user($expert_id);
        $transferred = 0;
        $errors = [];
        
        foreach ($expert_posts as $post_data) {
            $result = $this->transfer_credit($post_data, $expert_id, $personnel_id);
            if ($result === true) {
                $transferred++;
            } else {
                $errors[] = $result;
            }
        }

        $duplicates = get_option(self::OPTION_DUPLICATES, []);
        $merged = get_option(self::OPTION_MERGED, []);
        
        if (isset($duplicates[$index])) {
            $pair = $duplicates[$index];
            $pair['merged_at'] = current_time('mysql');
            $pair['posts_transferred'] = $transferred;
            $merged[] = $pair;
            unset($duplicates[$index]);
            $duplicates = array_values($duplicates);
            update_option(self::OPTION_DUPLICATES, $duplicates);
            update_option(self::OPTION_MERGED, $merged);
        }

        $message = sprintf('Merged successfully. Transferred %d post credit(s) from expert to personnel user.', $transferred);
        if (!empty($errors)) {
            $message .= ' Some errors occurred: ' . implode(', ', array_slice($errors, 0, 3));
        }

        wp_send_json_success(['message' => $message, 'transferred' => $transferred, 'remaining' => count($duplicates)]);
    }

    private function transfer_credit($post_data, $old_user_id, $new_user_id) {
        $post_id = $post_data['id'];
        $repeater_field = $post_data['field'];
        $post_type = get_post_type($post_id);
        
        if (!isset($this->credit_fields[$post_type][$repeater_field])) {
            return "Unknown field configuration for {$repeater_field}";
        }
        
        $user_subfield = $this->credit_fields[$post_type][$repeater_field];
        $count = get_post_meta($post_id, $repeater_field, true);
        if (!$count) $count = 0;
        
        $updated = false;
        for ($i = 0; $i < $count; $i++) {
            $meta_key = $repeater_field . '_' . $i . '_' . $user_subfield;
            $current_value = get_post_meta($post_id, $meta_key, true);
            if ($current_value == $old_user_id) {
                update_post_meta($post_id, $meta_key, $new_user_id);
                $updated = true;
            }
        }
        
        if ($updated) {
            wp_update_post([
                'ID' => $post_id,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', true),
            ]);
            clean_post_cache($post_id);
            if (function_exists('acf_save_post')) {
                do_action('acf/save_post', $post_id);
            }
        }
        return true;
    }
}

new Duplicate_User_Manager();